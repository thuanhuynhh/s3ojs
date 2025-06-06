<script>
	$(function() {ldelim}
		$('#s3StorageSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		
		var regionsByProvider = {$s3RegionsByProvider|default:'{}'};
		var currentRegion = '{$s3_region|escape:"javascript"}';

		function updateRegionOptions(provider) {
			var regions = regionsByProvider[provider] || {};
			var regionSelect = $('#s3_region');
			regionSelect.empty();
			
			if (Object.keys(regions).length === 0) {
				regionSelect.append($('<option></option>').attr('value', '').text('N/A'));
				regionSelect.prop('disabled', true);
			} else {
				$.each(regions, function(value, label) {
					regionSelect.append($('<option></option>').attr('value', value).text(label));
				});
				regionSelect.prop('disabled', false);
			}
			
			// Try to re-select the current region if it exists in the new list
			if (regionSelect.find('option[value="' + currentRegion + '"]').length) {
				regionSelect.val(currentRegion);
			}
		}
		
		// Provider change handler
		$('#s3_provider').change(function() {
			var provider = $(this).val();
			if (provider === 'custom') {
				$('#s3_custom_endpoint_section').show();
			} else {
				$('#s3_custom_endpoint_section').hide();
			}
			updateRegionOptions(provider);
		}).trigger('change');
	{rdelim});
</script>

<form class="pkp_form" id="s3StorageSettings" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="s3StorageSettingsFormNotification"}

	<div id="description">
		<h3>{translate key="plugins.generic.s3Storage.settings.title"}</h3>
		<p>{translate key="plugins.generic.s3Storage.settings.description"}</p>
	</div>

	{fbvFormArea id="s3StorageSettingsFormArea"}
		<h4>{translate key="plugins.generic.s3Storage.settings.title"}</h4>
		
		{fbvFormSection title="plugins.generic.s3Storage.settings.provider" for="s3_provider" required=true}
			{fbvElement type="select" id="s3_provider" from=$s3Providers selected=$s3_provider translate=false label="plugins.generic.s3Storage.settings.provider.description" required=true}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.s3Storage.settings.customEndpoint" for="s3_custom_endpoint" id="s3_custom_endpoint_section"}
			{fbvElement type="text" id="s3_custom_endpoint" value=$s3_custom_endpoint label="plugins.generic.s3Storage.settings.customEndpoint.description"}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.s3Storage.settings.bucket" for="s3_bucket" required=true}
			{fbvElement type="text" id="s3_bucket" value=$s3_bucket label="plugins.generic.s3Storage.settings.bucket.description" required=true}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.s3Storage.settings.key" for="s3_key" required=true}
			{fbvElement type="text" id="s3_key" value=$s3_key label="plugins.generic.s3Storage.settings.key.description" required=true}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.s3Storage.settings.secret" for="s3_secret" required=true}
			{fbvElement type="text" password=true id="s3_secret" value=$s3_secret label="plugins.generic.s3Storage.settings.secret.description" required=true}
		{/fbvFormSection}

		{fbvFormSection title="plugins.generic.s3Storage.settings.region" for="s3_region" required=true}
			{fbvElement type="select" id="s3_region" selected=$s3_region translate=false label="plugins.generic.s3Storage.settings.region.description" required=true}
		{/fbvFormSection}

		<h4>Advanced Features</h4>

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="s3_hybrid_mode" checked=$s3_hybrid_mode label="plugins.generic.s3Storage.settings.hybridMode.description"}
		{/fbvFormSection}

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="s3_fallback_enabled" checked=$s3_fallback_enabled label="plugins.generic.s3Storage.settings.fallbackEnabled.description"}
		{/fbvFormSection}

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="s3_auto_sync" checked=$s3_auto_sync label="plugins.generic.s3Storage.settings.autoSync.description"}
		{/fbvFormSection}

		<h4>{translate key="plugins.generic.s3Storage.cron.title"}</h4>

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="s3_cron_enabled" checked=$s3_cron_enabled label="plugins.generic.s3Storage.settings.cronEnabled.description"}
		{/fbvFormSection}

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="s3_cleanup_orphaned" checked=$s3_cleanup_orphaned label="plugins.generic.s3Storage.settings.cleanupOrphaned.description"}
		{/fbvFormSection}

		<h4>Optional Settings</h4>

		{fbvFormSection title="plugins.generic.s3Storage.settings.cdnDomain" for="s3_cdn_domain"}
			{fbvElement type="text" id="s3_cdn_domain" value=$s3_cdn_domain label="plugins.generic.s3Storage.settings.cdnDomain.description"}
		{/fbvFormSection}

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="s3_use_ssl" checked=$s3_use_ssl label="plugins.generic.s3Storage.settings.useSSL.description"}
		{/fbvFormSection}

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="s3_public_read" checked=$s3_public_read label="plugins.generic.s3Storage.settings.publicRead.description"}
		{/fbvFormSection}

		{fbvFormButtons}
			{assign var=buttonId value="submitFormButton"|concat:"-"|uniqid}
			{fbvElement type="submit" class="submitFormButton" id=$buttonId}
			<input type="button" value="{translate key="common.cancel"}" class="cancelFormButton" onclick="window.parent.$.pkp.classes.Handler.getHandler($('#settingsContainer')).modalHandler.closeModal()" />
		{/fbvFormButtons}
	{/fbvFormArea}
</form>

<div class="separator"></div>

<div id="s3TestConnection">
	<h4>Connection Test</h4>
	<p>Test connection to your storage service with current settings.</p>
	<button type="button" class="pkp_button" onclick="testS3Connection()">
		Test Connection
	</button>
	<div id="connectionResult" style="margin-top: 10px;"></div>
</div>

<div class="separator"></div>

<div id="s3MediaSync">
	<h4>{translate key="plugins.generic.s3Storage.sync.title"}</h4>
	<p>{translate key="plugins.generic.s3Storage.sync.description"}</p>
	<button type="button" class="pkp_button" onclick="startMediaSync()">
		{translate key="plugins.generic.s3Storage.sync.start"}
	</button>
	<div id="syncResult" style="margin-top: 10px;"></div>
</div>

<div class="separator"></div>

<div id="s3StorageCleanup">
	<h4>{translate key="plugins.generic.s3Storage.cleanup.title"}</h4>
	<p>{translate key="plugins.generic.s3Storage.cleanup.description"}</p>
	<button type="button" class="pkp_button pkp_button_warning" onclick="startStorageCleanup()">
		{translate key="plugins.generic.s3Storage.cleanup.start"}
	</button>
	<div id="cleanupResult" style="margin-top: 10px;"></div>
</div>

<script type="text/javascript">
function testS3Connection() {
	var provider = $('#s3_provider').val();
	var customEndpoint = $('#s3_custom_endpoint').val();
	var bucket = $('#s3_bucket').val();
	var key = $('#s3_key').val();
	var secret = $('#s3_secret').val();
	var region = $('#s3_region').val();
	
	if (!bucket || !key || !secret || !region) {
		$('#connectionResult').html('<div class="pkp_notification pkp_notification_error">' +
			'Please fill in all required fields first.' + '</div>');
		return;
	}
	
	$('#connectionResult').html('<div class="pkp_notification pkp_notification_info">' +
		'Testing connection...' + '</div>');
	
	$.post(
		'{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="testConnection"}',
		{
			's3_provider': provider,
			's3_custom_endpoint': customEndpoint,
			's3_bucket': bucket,
			's3_key': key,
			's3_secret': secret,
			's3_region': region,
			'csrfToken': '{$csrfToken}'
		},
		function(data) {
			if (data.status && data.content.status) {
				$('#connectionResult').html('<div class="pkp_notification pkp_notification_success">' +
					'{translate key="plugins.generic.s3Storage.settings.connectionTest.success"}' + '</div>');
			} else {
				$('#connectionResult').html('<div class="pkp_notification pkp_notification_error">' +
					'{translate key="plugins.generic.s3Storage.settings.connectionTest.failed"}' + '</div>');
			}
		},
		'json'
	).fail(function() {
		$('#connectionResult').html('<div class="pkp_notification pkp_notification_error">' +
			'{translate key="plugins.generic.s3Storage.settings.connectionTest.failed"}' + '</div>');
	});
}

function startMediaSync() {
	$('#syncResult').html('<div class="pkp_notification pkp_notification_info">' +
		'{translate key="plugins.generic.s3Storage.sync.inProgress"}' + '</div>');
	
	$.post(
		'{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="sync"}',
		{
			'csrfToken': '{$csrfToken}'
		},
		function(data) {
			if (data.status) {
				$('#syncResult').html('<div class="pkp_notification pkp_notification_success">' +
					data.content + '</div>');
			} else {
				$('#syncResult').html('<div class="pkp_notification pkp_notification_error">' +
					data.content + '</div>');
			}
		},
		'json'
	).fail(function() {
		$('#syncResult').html('<div class="pkp_notification pkp_notification_error">' +
			'{translate key="plugins.generic.s3Storage.sync.failed"}' + '</div>');
	});
}

function startStorageCleanup() {
	if (!confirm('This will permanently delete orphaned files from your storage. Are you sure?')) {
		return;
	}
	
	$('#cleanupResult').html('<div class="pkp_notification pkp_notification_info">' +
		'{translate key="plugins.generic.s3Storage.cleanup.inProgress"}' + '</div>');
	
	$.post(
		'{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="cleanup"}',
		{
			'csrfToken': '{$csrfToken}'
		},
		function(data) {
			if (data.status) {
				$('#cleanupResult').html('<div class="pkp_notification pkp_notification_success">' +
					data.content + '</div>');
			} else {
				$('#cleanupResult').html('<div class="pkp_notification pkp_notification_error">' +
					data.content + '</div>');
			}
		},
		'json'
	).fail(function() {
		$('#cleanupResult').html('<div class="pkp_notification pkp_notification_error">' +
			'{translate key="plugins.generic.s3Storage.cleanup.failed"}' + '</div>');
	});
}
</script> 