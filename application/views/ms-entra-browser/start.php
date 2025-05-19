<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="row">
	<div class="col-md-12">		
		<ol class="breadcrumb"></ol>	
	</div>
</div>
<div class="row">
	<div class="col-md-12 entra-browser">
		<div class="panel panel-light">
			<div class="panel-heading">
				<div class="clearfix">
					<div class="pull-left">
						<h3>Microsoft Entra ID Browser</h3>
					</div>
					<div class="pull-right">
						<a href="javascript:window.close();" class="btn btn-default">Close</a>
					</div>
				</div>
			</div>
			<div id="modal-alert-container" class="bluedot-error">
				<div class="alert alert-success">
					<h4 class="alert-heading">Error</h4>
					<div class="modal-alert-content"></div>
				</div>
			</div>
			<div class="panel-body">
				<div class="row">
					<div class="col-md-4 vr">
						<div class="search-area entra show">
							<h3>Search For Users</h3>
							<div class="row">
								<div class="col-md-12 text-left">
									<?php if (!empty($clients)) { ?>
										<?php echo form_open('/ms-entra-browser/search', array('id' => 'entra-search-form', 'autocomplete' => 'off', 'aria-autocomplete' => 'off', 'class' => 'vertical')); ?>
											<div class="form-group">
												<label class="control-label" for="entra-client-list">Customer</label>
												<select name="client_code" id="entra-client-list" class="selectpicker form-control" data-live-search="true" data-size="8">
													<option value="">- - - Select Customer - - -</option>
													<?php foreach ($clients as $client): ?>
														<option value="<?php echo $client['code']; ?>"><?php echo $client['client']; ?></option>
													<?php endforeach; ?>
												</select>
											</div>
											<div class="entra-search-options">
												<div class="form-group">
													<label class="control-label" for="entra-search-type">Search Type</label>	
													<?php echo form_dropdown('entra_search_type', $search_types, $set_search_type, 'class="selectpicker form-control" data-live-search="true" data-size="8" id="entra-search-type"'); ?>
												</div>

												<div id="entra-search-term" class="form-group">
													<label class="control-label" for="entra-search-value">Search Term <span class="help-block inline">( Value is case-insensitive. )</span></label>	
													<input type="text" class="form-control" name="entra_search_value" id="entra-search-value" placeholder="Enter Value" value="">
												</div>

												<div id="entra-search-submit-btn" class="text-center">
													<button type="submit" disabled="" form="entra-search-form" class="btn btn-primary" data-loading-text="Searching...">Search</button>
												</div>
											</div>
										<?php echo form_close(); ?>
									<?php } else { ?>
										<p class="text-center">Sorry there are no clients with Microsoft Entra ID enabled.</p>
									<?php } ?>
								</div>
							</div>
						</div>
					</div>
					<div class="col-md-4 vr">
						<div class="search-results">
							<h3>Search Results</h3>														
							<div class="row">
								<div class="col-md-12">
									<div class="entra-search-results">
										<table class="table valign-middle gray-header">
											<thead>
												<tr>
													<th class="text-left">Display Name</th>
													<th class="text-left">Job Title</th>
													<th class="text-right">&nbsp;</th>
												</tr>
											</thead>
											<tbody></tbody>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="col-md-4">
						<div class="entra-browser-placeholder show">
							<i class="fal fa-browser"></i>
						</div>					

						<div class="entra-data-display-area">
							<div class="row">
								<div class="col-md-12">
									<ul class="nav nav-tabs" id="entra-tabs">
										<li class="active">
											<a href="#table" data-toggle="tab">Entra ID Details</a>
										</li>     		
										<li class="pull-right">
											<a href="#json" data-toggle="tab" title="View Complete Entra ID JSON">JSON</a>
										</li>   	
									</ul>
									<div class="tab-content content-container" id="entra-tab-contents"></div>			
								</div>
							</div>	
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script type="text/html" id="entra-row-template">
	<tr>		
		<td class="text-left">{{display_name}}</td>
		<td class="text-left">{{job_title}}</td>
		<td class="text-center">
			<button type="button" class="btn btn-xs btn-default" data-user-id="{{id}}" data-client="{{client_code}}" data-loading-text="Retrieving...">Details</button>
		</td>
	</tr>
</script>

<script type="text/html" id="entra-detail-template">
	<div class="tab-pane active" id="table">
		<div class="entra-data-wrapper">
			<div class="row">
				<div class="col-md-12">
					{{#details.display_name}}
					<div class="form-group">
						<label class="control-label">Display Name</label>
						<div>{{details.display_name}}</div>
					</div>
					{{/details.display_name}}
					{{#details.job_title}}
					<div class="form-group">
						<label class="control-label">Job Title</label>
						<div>{{details.job_title}}</div>
					</div>
					{{/details.job_title}}
					{{#details.mail}}
					<div class="form-group">
						<label class="control-label">Email Address</label>
						<div>{{details.mail}}</div>
					</div>
					{{/details.mail}}
					{{#details.business_phone}}
					<div class="form-group">
						<label class="control-label">Business Phone</label>
						<div>{{details.business_phone}}</div>
					</div>
					{{/details.business_phone}}
					{{#details.mobile_phone}}
					<div class="form-group">
						<label class="control-label">Mobile Phone</label>
						<div>{{details.mobile_phone}}</div>
					</div>
					{{/details.mobile_phone}}
					{{#details.office_location}}
					<div class="form-group">
						<label class="control-label">Office Location</label>
						<div>{{details.office_location}}</div>
					</div>
					{{/details.office_location}}
					{{#details.preferred_language}}
					<div class="form-group">
						<label class="control-label">Preferred Language</label>
						<div>{{details.preferred_language}}</div>
					</div>
					{{/details.preferred_language}}
					{{#details.given_name}}
					<div class="form-group">
						<label class="control-label">Given Name</label>
						<div>{{details.given_name}}</div>
					</div>
					{{/details.given_name}}
					{{#details.surname}}
					<div class="form-group">
						<label class="control-label">Surname</label>
						<div>{{details.surname}}</div>
					</div>
					{{/details.surname}}
					{{#details.user_principle_name}}
					<div class="form-group">
						<label class="control-label">User Principle Name</label>
						<div>{{details.user_principle_name}}</div>
					</div>
					{{/details.user_principle_name}}
					{{#details.id}}
					<div class="form-group">
						<label class="control-label">ID</label>
						<div>{{details.id}}</div>
					</div>
					{{/details.id}}
					{{#details.account_enabled}}
					<div class="form-group">
						<label class="control-label">Account Enabled</label>
						<div>true</div>
					</div>
					{{/details.account_enabled}}
					{{^details.account_enabled}}
					<div class="form-group">
						<label class="control-label">Account Enabled</label>
						<div>false</div>
					</div>
					{{/details.account_enabled}}
				</div>
			</div>
		</div>
		{{{details.action_form}}}
	</div>	
	<div class="tab-pane" id="json">						
		<div class="entra-json-wrapper">					
			<pre class="entra-data">{{json}}</pre>
		</div>
	</div>
</script>