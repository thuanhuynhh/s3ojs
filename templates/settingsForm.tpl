<script>
	$(function() {ldelim}
		$('#s3StorageSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		
		var regionsByProvider = {$s3RegionsByProvider|default:'{}'};
		var currentRegion = '{$s3_region|escape:"javascript"}';

		function updateRegionOptions(provider) {
			var regions = regionsByProvider[provider] || {};
			var regionSelect = $('select[name="s3_region"]');
			var regionSection = regionSelect.closest('.section');
			var regionLabel = regionSection.find('label');
			
			regionSelect.empty();
			
			if (Object.keys(regions).length === 0 || provider === 'custom') {
				regionSelect.append($('<option></option>').attr('value', '').text('N/A'));
				regionSelect.prop('disabled', true);
				regionSelect.removeAttr('required');
				// Remove required indicator for custom provider
				regionLabel.find('.req').remove();
				regionSection.removeClass('required');
			} else {
				$.each(regions, function(value, label) {
					regionSelect.append($('<option></option>').attr('value', value).text(label));
				});
				regionSelect.prop('disabled', false);
				regionSelect.attr('required', 'required');
				// Add required indicator for non-custom providers
				if (regionLabel.find('.req').length === 0) {
					regionLabel.append(' <span class="req">*</span>');
				}
				regionSection.addClass('required');
			}
			
			// Try to re-select the current region if it exists in the new list
			if (regionSelect.find('option[value="' + currentRegion + '"]').length) {
				regionSelect.val(currentRegion);
			}
		}
		
		// Provider change handler
		$('select[name="s3_provider"]').change(function() {
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
	{/fbvFormArea}
</form>

<div class="separator"></div>

<div id="s3TestConnection">
	<h4>Connection Test</h4>
	<p>Test connection to your storage service with current settings.</p>
	<button id="testConnectionBtn" type="button" class="pkp_button">
		Test Connection
	</button>
	<div id="connectionResult" style="margin-top: 10px;"></div>
</div>

<div class="separator"></div>

<div id="s3MediaSync">
	<h4>{translate key="plugins.generic.s3Storage.sync.title"}</h4>
	<p>{translate key="plugins.generic.s3Storage.sync.description"}</p>
	<button id="startSyncBtn" type="button" class="pkp_button">
		{translate key="plugins.generic.s3Storage.sync.start"}
	</button>
	<div id="syncResult" style="margin-top: 10px;"></div>
</div>

<div class="separator"></div>

<div id="s3StorageCleanup">
	<h4>{translate key="plugins.generic.s3Storage.cleanup.title"}</h4>
	<p>{translate key="plugins.generic.s3Storage.cleanup.description"}</p>
	<button id="startCleanupBtn" type="button" class="pkp_button pkp_button_warning">
		{translate key="plugins.generic.s3Storage.cleanup.start"}
	</button>
	<div id="cleanupResult" style="margin-top: 10px;"></div>
</div>

<script type="text/javascript">
(function($) {
    function testS3Connection() {
        var formData = $('#s3StorageSettings').serializeArray().reduce(function(obj, item) {
            obj[item.name] = item.value;
            return obj;
        }, {});

        $('#connectionResult').html('<div class="pkp_notification pkp_notification_info">Testing connection...</div>');

        $.post('{$actionUrls.testConnection|escape:javascript}', formData, function(response) {
            var content = response.content || {};
            var message = content.message || '{translate key="plugins.generic.s3Storage.settings.connectionTest.failed"}';
            
            // Print to console as requested
            console.log('S3 Connection Test Response:', response);

            if (response.status && content.status) {
                $('#connectionResult').html('<div class="pkp_notification pkp_notification_success">' + message + '</div>');
            } else {
                // Display the detailed error message from the server
                $('#connectionResult').html('<div class="pkp_notification pkp_notification_error">' + message + '</div>');
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            var errorMsg = 'AJAX Error: ' + textStatus + ' - ' + errorThrown;
            console.log(errorMsg, jqXHR.responseText);
            $('#connectionResult').html('<div class="pkp_notification pkp_notification_error">' + errorMsg + '</div>');
        });
    }

    function startMediaSync() {
        $('#syncResult').html('<div class="pkp_notification pkp_notification_info">{translate key="plugins.generic.s3Storage.sync.inProgress"}</div>');
        $.post('{$actionUrls.sync|escape:javascript}', { csrfToken: '{$csrfToken}' }, function(response) {
            console.log('S3 Sync Response:', response); // Debugging
            var message = response.content || '{translate key="plugins.generic.s3Storage.sync.failed"}';
            if (response.status) {
                $('#syncResult').html('<div class="pkp_notification pkp_notification_success">' + message + '</div>');
            } else {
                $('#syncResult').html('<div class="pkp_notification pkp_notification_error">' + message + '</div>');
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            var errorMsg = 'AJAX Error: ' + textStatus + ' - ' + errorThrown;
            console.log(errorMsg, jqXHR.responseText);
            $('#syncResult').html('<div class="pkp_notification pkp_notification_error">' + errorMsg + '</div>');
        });
    }

    function startStorageCleanup() {
        if (!confirm('{translate key="plugins.generic.s3Storage.cleanup.confirm"}')) return;
        $('#cleanupResult').html('<div class="pkp_notification pkp_notification_info">{translate key="plugins.generic.s3Storage.cleanup.inProgress"}</div>');
        $.post('{$actionUrls.cleanup|escape:javascript}', { csrfToken: '{$csrfToken}' }, function(response) {
            console.log('S3 Cleanup Response:', response); // Debugging
            var message = response.content || '{translate key="plugins.generic.s3Storage.cleanup.failed"}';
            if (response.status) {
                $('#cleanupResult').html('<div class="pkp_notification pkp_notification_success">' + message + '</div>');
            } else {
                $('#cleanupResult').html('<div class="pkp_notification pkp_notification_error">' + message + '</div>');
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            var errorMsg = 'AJAX Error: ' + textStatus + ' - ' + errorThrown;
            console.log(errorMsg, jqXHR.responseText);
            $('#cleanupResult').html('<div class="pkp_notification pkp_notification_error">' + errorMsg + '</div>');
        });
    }

    // Attach handlers to buttons
    $(function() {
        $('#testConnectionBtn').on('click', testS3Connection);
        $('#startSyncBtn').on('click', startMediaSync);
        $('#startCleanupBtn').on('click', startStorageCleanup);
    });
})(jQuery);
</script> 