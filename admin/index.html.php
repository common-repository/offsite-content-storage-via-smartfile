<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<style type="text/css">
	.schema-form-submit{
		float:right;
	}
</style>
<div class="container-fluid" style="background-color:none;">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<div class="row" ng-app="FsegApp">
		<div class="col-md-1">
		</div>
			<div class="col-md-10">
				<div ng-controller="FormController">
					<div ng-class="msgclass" role="alert"  ng-show="msg.length>0">
						<button ng-click="closeAlert()" type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						<span ng-bind="msg"></span>
					</div>
				  <form name="myForm" sf-schema="schema" sf-form="form" sf-model="model" ng-submit="onSubmit(myForm)">
				  	
				  </form>
				  <div><br><br><br>
				  	<span style="float:right;"><i>Takes a few seconds to save.</i></span>
				  </div>
				</div>
			</div>
			<div class="col-md-1">
		</div>
	</div>
</div>

