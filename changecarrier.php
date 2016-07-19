<?php
if (!defined('_PS_VERSION_'))
    exit;

class ChangeCarrier extends Module
{
    // MÉTHODES NATIVES MODULE PRESTASHOP

    public function __construct() // CONSTRUCTEUR
    {
        $this->name = 'changecarrier';                                                          // Identifiant unique du module
        $this->tab = 'shipping_logistics';                                                      // Catégorie associée au module
        $this->version = '1.0';                                                                 // Version du module
        $this->author = 'Klemart3D';                                                            // Auteur
        $this->author_uri = 'contact@klemart3d.fr';                                             // Mail de contact

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Change carrier');                                        // Nom affiché du module
        $this->description = $this->l('Changes easily and quickly the carrier of an order');    // Descriptif
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);           // Compatibilité du module
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');              // Message de désinstallation
        $this->module_key = "0cb00868c2c0f986d3fd3edbda28959a";                                 // Clé d'identification unique du module
    }
        
    public function install() // INSTALLATEUR
    {
        if(!parent::install() || !$this->registerHook('adminOrder') || !Configuration::updateValue('CHANGECARRIER_ACTIVE', '1'))
            return false;
        return true;
    }

    public function uninstall() // DÉSINSTALLATEUR
    {
        if(!parent::uninstall() || !Configuration::deleteByName('CHANGECARRIER_ACTIVE'))
            return false;
        return true;
    }

    // MÉTHODES DE CONFIGURATION DU MODULE

    public function getContent() // CONFIGURATEUR
    {
        $this->_html = '';

        $this->smarty->assign(array(                                            // Variables envoyées au template
            'module_dir' => _PS_MODULE_DIR_.$this->name,
            'module_name' => $this->name,
            'email' => $this->author_uri
        ));

        $this->_html .= '<h2>'.$this->displayName.' v'.$this->version.'</h2>';
        $this->_html .= $this->_preProcess();
        $this->_html .= $this->displayConfig();
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    private function _preProcess() // Forumlaire de nouvelle configuration soumis
    {
        if (Tools::isSubmit($this->name))
        {
            $active_carrier = Tools::getValue('CHANGECARRIER_ACTIVE') == "on" ? 1 : 0;     // Conversion case (dé)cochée en binaire
            {
                Configuration::updateValue('CHANGECARRIER_ACTIVE', (int)$active_carrier);  // On stock la nouvelle configuration
            }
            $this->_html .= $this->displayConfirmation($this->l('Settings updated.'));     // On affiche le message de mise à jour
        }
    }

    private function renderForm() // Formulaire de la page config
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Module settings'),
                    'icon' => 'icon-truck'
                ),
                'input' => array(
                    array(
                        'type'    => 'checkbox',                                // Entrée de type "case à cocher"
                        'name'    => 'CHANGECARRIER',
                        'values'  => array(
                            'query' => array(
                              array(
                                'id_option' => 'ACTIVE',                        // Valeur de la case à cocher
                                'name' => $this->l('Show only active carriers') // Nom de la case à cocher
                              ),
                            ),
                            'id'    => 'id_option',
                            'name'  => 'name'
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),                                // Texte du bouton
                    'name' => $this->name                                       // Nom du bouton de soumission
                )
            ),
        );

        $helper = new HelperForm();
        $carrier_active = Configuration::get('CHANGECARRIER_ACTIVE') == 1 ? TRUE : FALSE; // On récupère la variable enregistrée
        $helper->fields_value['CHANGECARRIER_ACTIVE'] = (bool)$carrier_active;            // Suivant le résultat, on coche ou non la case
        return $helper->generateForm(array($fields_form));                                // On renvoie le formulaire
    }

    private function displayConfig() // Récupèration le template de configuration
    {
        return $this->display(__FILE__, 'config.tpl');
    }

    // MÉTHODES DE GREFFE DU MODULE
        
    public function hookAdminOrder($params) // Traitement du module sur une page de commande
    {
        $order = new Order($params['id_order']);                                // Récupération de la commande active

        if(Tools::isSubmit($this->name))                                        // Si un changement de transporteur est soumis
        {
            $new_carrier_id = (int)Tools::getValue('changecarrier_id');         // Récupération de l'id du nouveau transporteur

            if((int)$new_carrier_id != (int)$order->id_carrier)
            {
                // Mise à jour n°1 : table "ps_order_carrier" :
                $order_carrier_id = $this->getIdOrderCarrier($order->id);
                $order_carrier = new OrderCarrier((int)$order_carrier_id);
                $order_carrier->id_carrier = (int)$new_carrier_id;
                $order_carrier->update();

                // Mise à jour n°2 : table "ps_orders" :
                $order->id_carrier = (int)$new_carrier_id;
                $order->update();

                Tools::redirect(_PS_BASE_URL_.$_SERVER['REQUEST_URI']);         // On rafraîchit la page
            }
        }

        $this->smarty->assign(array(                                            // Variables envoyées au template
            'shop_uri' => _PS_BASE_URL_.$_SERVER['REQUEST_URI'],
            'module_name' => $this->name,
            'carrier_list' => $this->getCarrierSelector($order->id_carrier),
            'id_order' => $order->id
        ));

       return $this->displayHookAdminOrder();
    }
    
    private function getCarrierSelector($currentcarrier) // Récupération de la liste des transporteurs
    {
        global $cookie;
        $carrier_active = Configuration::get('CHANGECARRIER_ACTIVE') == 1 ? TRUE : FALSE;           // Transporteurs actifs ou inactifs
        $carriers = Carrier::getCarriers(intval($cookie->id_lang), (bool)$carrier_active, false);
        $options = "";
        foreach ($carriers as $carrier)
        {
            $options.='<option value="'.$carrier['id_carrier'].'" '.($carrier['id_carrier']==$currentcarrier?'selected="selected"':'').'>'.$carrier['name'].'</option>';;
        }
        $carrierselecter='<select name="changecarrier_id">'.$options.'</select>';
        return $carrierselecter; // Renvoie directement la liste complète dans un <select>
    }

    private function getIdOrderCarrier($id_order) // Modèle (DAO) de récupèration de l'id transporteur-commande
    {
        return Db::getInstance()->getValue('
                SELECT `id_order_carrier`
                FROM `'._DB_PREFIX_.'order_carrier`
                WHERE `id_order` = '.(int)$id_order);
    }

    private function displayHookAdminOrder() // Récupère le template du hook
    {
        return $this->display(__FILE__, 'adminOrder.tpl');
    }
}
