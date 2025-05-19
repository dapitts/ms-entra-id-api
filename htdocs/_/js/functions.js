// @prepros-prepend 'jquery-1.11.3.js'
// @prepros-prepend 'jquery-ui-1.12.1.js'
// @prepros-prepend 'bootstrap.js'
// @prepros-prepend 'highcharts-4.1.9.js'
// @prepros-prepend 'highcharts-3d-4.1.9.js'
// @prepros-prepend 'modules/exporting.js'
// @prepros-prepend 'modules/funnel.js'
// @prepros-prepend 'canvg/rgbcolor.js'
// @prepros-prepend 'canvg/StackBlur.js'
// @prepros-prepend 'canvg/canvg.js'
// @prepros-prepend 'jquery.form.js'
// @prepros-prepend 'bootstrap-select/bootstrap-select.js'
// @prepros-prepend 'jvectormap/jquery.fullscreen.js'
// @prepros-prepend 'js.cookie.js'
// @prepros-prepend 'jquery.datetimepicker.full.js'
// @prepros-prepend 'soc-live.js'
// @prepros-prepend 'clipboard.js'
// @prepros-prepend 'jquery-te-1.4.0'
// @prepros-prepend 'bluedot_console.js'
// @prepros-prepend 'summernote.js'
// @prepros-prepend 'infinite-scroll-3.0.6.js'
// @prepros-prepend 'jquery.visible.js'
// @prepros-prepend 'mustache.js'
// @prepros-prepend 'endpoint_finder.js'
// @prepros-prepend 'ldap_browser.js'
// @prepros-prepend 'entra_browser.js'

// remap jQuery to $
(function($)
{

	$(document).ready(function ()
	{		

		// Button Loading State ::
		$(function () {
			$(document).on('click', 'button[type="submit"]', function () {
				$(this).button('loading');
			});
		});

		/*
			Default General Ajax Actions
			----------------------------------
		*/
		var general_action = { 
	        beforeSubmit:  showGeneralRequest, 
	        success:       showGeneralResponse,
	        error:		   showGeneralError,
	        type: 		   'POST',
	        timeout:   	   10000 
	    };   

	    $(document).on("submit", "#general_action", function() {  
	        $(this).ajaxSubmit(general_action); 
	        return false; 
	    });

		$(function () 
		{	
			$(document).on('click', '#show-api-test', function () 
			{
				$('#api-test-display').show();
			});
		});

		$(function () 
		{	
			$(document).on('click', '#run-api-test, #load-more-machines', function () 
			{
				var _btn 		= $(this);
				var _api 		= _btn.data('api');
				var _client 	= _btn.data('client');
				var _btn_id		= _btn[0]['id'];
				var _offset 	= 0;
				var _results;
				
				_btn.button('loading');

				$('.machine-list').find('tbody').html('');
				$('.machine-count').text('0');
				$('.machines-total').text('0');
				$('.result-count').text('0');
				
				if (_btn_id === 'load-more-machines')
				{
					var _offset = _btn.data('offset');
				}
				
				if (_btn_id === 'run-api-test')
				{
					$('#api-test-results .api-json-results').html('');		
					$('#api-test-results').hide();		
					$('.running-test').show();
				}
				
				if ($('.test-success').is(':visible'))
				{
			        $('.test-success').hide();
				}

			    if ($('.test-failed').is(':visible'))
				{
			        $('.test-failed').hide();
				}

				if ($('.machine-list').is(':hidden'))
				{
					$('.machine-list').show();
				}
				
				if (_api === 'sentinelone')
				{
					url = '/customer-management/endpoint/sentinelone/api-test/'+_offset+'/'+_client;
				}
				
				if (_api === 'ms-defender')
				{
					url = '/customer-management/endpoint/ms-defender/api-test/'+_offset+'/'+_client;
				}

				if (_api === 'carbon-black')
				{
					url = '/customer-management/endpoint/carbon-black/api-test/'+_offset+'/'+_client;
				}

				if (_api === 'crowdstrike')
				{
					url = '/customer-management/endpoint/crowdstrike/api-test/'+_offset+'/'+_client;
				}
								
				if (_api === 'service_now')
				{
					url = '/customer-management/servicenow/api-test/'+_client;
				}

				if (_api === 'pagerduty')
				{
					url = '/customer-management/pagerduty/api-test/'+_client;
				}

				if (_api === 'insightvm')
				{
					url = '/customer-management/insightvm/api-test/'+_client;
				}

				if (_api === 'ldap')
				{
					url = '/customer-management/ldap/api-test/'+_client;
				}

				if (_api === 'shield-cyber')
				{
					url = '/customer-management/shield-cyber/api-test/'+_client;
				}

				if (_api === 'ms-entra')
				{
					url = '/customer-management/ms-entra/api-test/'+_client;
				}

			    $.get(url,function(data,status) 
			    {
					if (status) 
					{
						var _response 	= data;
						var _reponseObj = jQuery.parseJSON(_response);
						var _success 	= _reponseObj.success;
						var return_str 	= JSON.stringify(_reponseObj.response, null, 2);

						if (_success)
						{
							if (_api === 'ms-defender' || _api === 'sentinelone' || _api === 'carbon-black' || _api === 'crowdstrike')
							{	
								set_total = parseInt($('.machine-count').text()) + parseInt(_reponseObj.machine_count);
								$('.machine-count').text(set_total);
								
								if (_reponseObj.machine_total)
								{
									$('.machines-total').text(_reponseObj.machine_total);
									$('#load-more-machines').data('total-machines',_reponseObj.machine_total);

									if (parseInt(_reponseObj.machine_count) <= parseInt(_reponseObj.machine_total))
									{
										$('#load-more-machines').hide();
									}
								}
								
							}

							if (_api === 'ldap' || _api === 'shield-cyber' || _api === 'ms-entra')
							{
								if (_reponseObj.result_count)
								{
									$('.result-count').text(_reponseObj.result_count);

									let results         = _reponseObj.results,
									    selector        = $('.machine-list tbody'),
									    rendered_html   = results.map(get_generic_row_template).join('');

									selector.append(rendered_html);
								}
							}

							if (_btn_id === 'run-api-test')
							{				
								$('.running-test').hide();
								$('.test-success').show();
								$('.api-json-results').html(return_str);
							}
							
							_btn.button('reset');
							
							
							if (_btn_id === 'load-more-machines')
							{
								_btn.data('offset',_offset+50);
							}
							
							if (_offset+50 >= _btn.data('total-machines'))
							{
								_btn.hide();
							}
														
							if (_api === 'service_now' || _api === 'pagerduty' || _api === 'insightvm' || _api === 'ldap' || _api === 'shield-cyber' || _api === 'ms-entra')
							{
								$('.icon-api-tested').show();
								$('.api-activation-panel').show();
							}
							
							if (_api === 'ms-defender' || _api === 'sentinelone' || _api === 'carbon-black' || _api === 'crowdstrike')
							{
								let machine_list    = _reponseObj.machine_data,
							        selector        = $('.machine-list tbody'),
								    rendered_html   = machine_list.map(get_generic_row_template).join('');

								selector.append(rendered_html);
							}
						}
						else
						{
							$('.machine-list').hide();
							$('.running-test').hide();
							$('.test-failed').show();

							if (_api === 'pagerduty' || _api === 'insightvm' || _api === 'carbon-black' || _api === 'crowdstrike' || _api === 'ldap' || _api === 'shield-cyber'  || _api === 'ms-entra')
							{
								$('.api-json-results').html(return_str);
							}
							else
							{
								$('.api-json-results').html(_reponseObj.response);
							}

							_btn.button('reset');
						}
						$('#api-test-results').show();
					}
					else
					{
						$('.running-test').hide();
						_btn.button('reset');							
						$('#api-test-results').html('{"success":false}').show();
					}
				});
			});
		});

	}); // end doc ready functions

// =================================================
// BLOCK Functions - Start Here
// =================================================

	get_generic_row_template = function(data) 
	{			
		var row_template = $('#row-template').html();		
		return Mustache.render(row_template, data);
	};

	closeModalAlertBox = function() {
		$('#modal-alert-container').fadeOut();
	};

	removeButtonSubmit = function()
	{
		$('form.element-action-form button[data-dismiss="modal"]').show();
		$('form.element-action-form button[type="submit"]').button('reset');
	};

	showValidationError = function(xMessage) {
		$('.modal-alert-content').html(xMessage);
		$('#modal-alert-container').show();
		removeButtonSubmit();
		setTimeout(function(){closeModalAlertBox();}, 3000);
	};

	closeReportSuccessAlertBox = function() {
		$('#report-alert-container').fadeOut();
	};

	showActionSuccess = function(xMessage) {
		$('.report-alert-content').html(xMessage);
		$('#report-alert-container').show();
		setTimeout(function(){closeReportSuccessAlertBox();}, 3000);
	};

	showButtonSubmit = function()
	{
		$('form.element-action-form button[data-dismiss="modal"]').hide();
		$('form.element-action-form button[type="submit"]').button('loading');
	};

	showGeneralRequest = function(formData, jqForm, options) { 
		showButtonSubmit();
		//return true;
	};

	showGeneralResponse = function(responseText)  { 
		var _response 	= responseText;
		var _reponseObj = jQuery.parseJSON(_response);
		var _success 	= _reponseObj.success;
		
		// Success
		if (_success) 
		{	
			if (_reponseObj.goto_url) 
			{
				window.location = _reponseObj.goto_url;
			}
			else
			{
				$('.modal').modal('hide');	
				showActionSuccess(_reponseObj.message);
			}
			
			$('input[name="'+_reponseObj.csrf_name+'"]').val(_reponseObj.csrf_value);
			
		}
		// Fail
		if (!_success) 
		{
			if (_reponseObj.csrf_name)
			{
				$('input[name="'+_reponseObj.csrf_name+'"]').val(_reponseObj.csrf_value);
			}
			showValidationError(_reponseObj.message);
			$('button[type="submit"]').button('reset');
		}
	};

	showGeneralError = function(jqXHR, textStatus, errorThrown) {
		//$('.modal-alert-content').html('<p>Oops, Something went wrong!</p>');
		
		$('.modal-alert-content').html('<p>ERROR: '+errorThrown+'</p>');
		
		$('#modal-alert-container').show();
		removeButtonSubmit();
		setTimeout(function(){closeModalAlertBox();}, 3000);
		
		// *** need to add sometype of refresh ***

	};

})(window.jQuery);