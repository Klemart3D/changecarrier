<div class="col-lg-5 pull-right">
	<div class="panel">

		<div class="panel-heading">
			<i class="icon-truck"></i> {l s='Change the carrier' mod='changecarrier'}
		</div>

		<fieldset>
			<form method="POST" action="{$shop_uri|escape:'htmlall'}" role="form" class="form-horizontal">
				<div class="form-group row">
					<div class="col-lg-9">
						{$carrier_list}
					</div>

					<div class="col-lg-3">
						<input type="hidden" name="id_order" value="{$id_order|escape:'htmlall'}" />
						<input class="btn btn-primary" type="submit" name="{$module_name}" onclick="if(this.value!=0) self.location.reload(); form.submit();" value="{l s='Change carrier' mod='changecarrier'}" class="button" />
					</div>
				</div>

			</form>
    	</fieldset>

	</div>
</div>