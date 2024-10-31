	function responseTransformer(data, headersGetter, status){

	}
	function isJson(text) {
		text = String(text);
		// console.log("isJson = ");
		// console.log(text);
		var n = text.indexOf("[");
		if (n == 0){
			return true;
		}
		if (/^[\],:{}\s]*$/.test(text.replace(/\\["\\\/bfnrtu]/g, '@').
		replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g, ']').
		replace(/(?:^|:|,)(?:\s*\[)+/g, ''))) {

		  //the json is ok
			// console.log("Valid json");
			return true;

		}else{
			// console.log("INValid json");
			return false;
		  //the json is not ok

		}

	}
	var app = angular.module("FsegApp", ['schemaForm']);
	var fsegAPIurl = fsegAdminUrl;
	app.controller("FormController", function($scope, $http,$timeout){
		// $scope.msg = "Updated form";
		$scope.msgclass = "alert alert-success";
		$scope.submittingFormX="Submitting";
		$scope.closeAlert=function(){
			$scope.msg = "";
		}
		$scope.load = function(){
			var data = {
				"action": "GET"
			}
			$http({
			  method: 'GET',
			  data: data,
			  url: fsegAPIurl+'&action=GET',
			}).then(
			function successCallback(response) {
				console.log(response.data);
				$scope.schema = response.data.schema;
				$scope.form = response.data.form;
				$scope.model = response.data.formData;

				$scope.onSubmit = function(form) {
				    $scope.$broadcast('schemaFormValidate');
				    if (form.$valid){
				    	$scope.submittingFormX="Submitting Form";
				    	var prepData = {};
					    prepData.formData = $scope.model;
					    prepData.schema = $scope.schema;
						prepData.form = $scope.form;
						console.log(prepData);
					    $http({
						  method: 'POST',
						  data: prepData,
						  url: fsegAPIurl+'&action=UPDATE',
						  responseType  : 'text',
						}).then(function successCallback(response) {
							$scope.submittingFormX="";
							// console.log(response.data);
							if (!isJson(response.data)){
								$scope.msg = "Unable to Update settings";
								$scope.msgclass = "alert alert-danger";
								return;
							}
							$scope.msgclass = "alert alert-success";
							$scope.msg = "Updated settings";
							$scope.schema = response.data.schema;
							$scope.form = response.data.form;
							$scope.model = response.data.formData;
							$timeout($scope.closeAlert,2000);
							scroll(0,0)
						  }, function errorCallback(response) {
						  	// console.log("ERROR!");
						  	$scope.submittingFormX="";
						  	$scope.msgclass = "alert alert-danger";
				    		$scope.msg = "Unable to Update settings";

						});
				    }else{

				    	// alert("Invalid Inputs")
				    }

				  }
			  }, function errorCallback(response) {

			  			$scope.msgclass = "alert alert-danger";
				    	$scope.msg = "Unable to get settings";

			});
		}
		$scope.load();
})
