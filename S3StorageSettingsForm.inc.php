<?php

/**
 * @file plugins/generic/s3Storage/S3StorageSettingsForm.inc.php
 *
 * Copyright (c) 2023 OJS/PKP
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class S3StorageSettingsForm
 * @ingroup plugins_generic_s3Storage
 *
 * @brief Form for S3-compatible Storage plugin settings with advanced features
 */

import('lib.pkp.classes.form.Form');

class S3StorageSettingsForm extends Form {

    /** @var int */
    var $_contextId;

    /** @var S3StoragePlugin */
    var $_plugin;

    /**
     * Constructor
     * @param $plugin S3StoragePlugin
     * @param $contextId int
     */
    function __construct($plugin, $contextId) {
        $this->_contextId = $contextId;
        $this->_plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidator($this, 's3_bucket', 'required', 'plugins.generic.s3Storage.settings.bucket.required'));
        $this->addCheck(new FormValidator($this, 's3_key', 'required', 'plugins.generic.s3Storage.settings.key.required'));
        $this->addCheck(new FormValidator($this, 's3_secret', 'required', 'plugins.generic.s3Storage.settings.secret.required'));
        $this->addCheck(new FormValidator($this, 's3_region', 'required', 'plugins.generic.s3Storage.settings.region.required'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Initialize form data.
     */
    function initData() {
        $this->_data = array(
            's3_provider' => $this->_plugin->getSetting($this->_contextId, 's3_provider') ?: 'aws',
            's3_custom_endpoint' => $this->_plugin->getSetting($this->_contextId, 's3_custom_endpoint'),
            's3_bucket' => $this->_plugin->getSetting($this->_contextId, 's3_bucket'),
            's3_key' => $this->_plugin->getSetting($this->_contextId, 's3_key'),
            's3_secret' => $this->_plugin->getSetting($this->_contextId, 's3_secret'),
            's3_region' => $this->_plugin->getSetting($this->_contextId, 's3_region'),
            's3_hybrid_mode' => $this->_plugin->getSetting($this->_contextId, 's3_hybrid_mode'),
            's3_fallback_enabled' => $this->_plugin->getSetting($this->_contextId, 's3_fallback_enabled'),
            's3_auto_sync' => $this->_plugin->getSetting($this->_contextId, 's3_auto_sync'),
            's3_cron_enabled' => $this->_plugin->getSetting($this->_contextId, 's3_cron_enabled'),
            's3_cron_frequency' => $this->_plugin->getSetting($this->_contextId, 's3_cron_frequency') ?: 'daily',
            's3_cleanup_orphaned' => $this->_plugin->getSetting($this->_contextId, 's3_cleanup_orphaned'),
            's3_cdn_domain' => $this->_plugin->getSetting($this->_contextId, 's3_cdn_domain'),
            's3_use_ssl' => $this->_plugin->getSetting($this->_contextId, 's3_use_ssl'),
            's3_public_read' => $this->_plugin->getSetting($this->_contextId, 's3_public_read'),
        );
    }

    /**
     * Assign form data to user-submitted data.
     */
    function readInputData() {
        $this->readUserVars(array(
            's3_provider',
            's3_custom_endpoint',
            's3_bucket',
            's3_key', 
            's3_secret',
            's3_region',
            's3_hybrid_mode',
            's3_fallback_enabled',
            's3_auto_sync',
            's3_cron_enabled',
            's3_cron_frequency',
            's3_cleanup_orphaned',
            's3_cdn_domain',
            's3_use_ssl',
            's3_public_read'
        ));
    }

    /**
     * Fetch the form.
     * @copydoc Form::fetch()
     */
    function fetch($request, $template = null, $display = false) {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->_plugin->getName());
        $templateMgr->assign('s3Providers', $this->_getS3Providers());
        $templateMgr->assign('s3Regions', $this->_getS3Regions());
        $templateMgr->assign('cronFrequencies', $this->_getCronFrequencies());
        return parent::fetch($request, $template, $display);
    }

    /**
     * Execute the form.
     */
    function execute(...$functionArgs) {
        $this->_plugin->updateSetting($this->_contextId, 's3_provider', $this->getData('s3_provider'), 'string');
        $this->_plugin->updateSetting($this->_contextId, 's3_custom_endpoint', trim($this->getData('s3_custom_endpoint'), "\"\';"), 'string');
        $this->_plugin->updateSetting($this->_contextId, 's3_bucket', trim($this->getData('s3_bucket'), "\"\';"), 'string');
        $this->_plugin->updateSetting($this->_contextId, 's3_key', trim($this->getData('s3_key'), "\"\';"), 'string');
        $this->_plugin->updateSetting($this->_contextId, 's3_secret', trim($this->getData('s3_secret'), "\"\';"), 'string');
        $this->_plugin->updateSetting($this->_contextId, 's3_region', $this->getData('s3_region'), 'string');
        $this->_plugin->updateSetting($this->_contextId, 's3_hybrid_mode', $this->getData('s3_hybrid_mode'), 'bool');
        $this->_plugin->updateSetting($this->_contextId, 's3_fallback_enabled', $this->getData('s3_fallback_enabled'), 'bool');
        $this->_plugin->updateSetting($this->_contextId, 's3_auto_sync', $this->getData('s3_auto_sync'), 'bool');
        $this->_plugin->updateSetting($this->_contextId, 's3_cron_enabled', $this->getData('s3_cron_enabled'), 'bool');
        $this->_plugin->updateSetting($this->_contextId, 's3_cron_frequency', $this->getData('s3_cron_frequency'), 'string');
        $this->_plugin->updateSetting($this->_contextId, 's3_cleanup_orphaned', $this->getData('s3_cleanup_orphaned'), 'bool');
        $this->_plugin->updateSetting($this->_contextId, 's3_cdn_domain', trim($this->getData('s3_cdn_domain'), "\"\';"), 'string');
        $this->_plugin->updateSetting($this->_contextId, 's3_use_ssl', $this->getData('s3_use_ssl'), 'bool');
        $this->_plugin->updateSetting($this->_contextId, 's3_public_read', $this->getData('s3_public_read'), 'bool');
        
        parent::execute(...$functionArgs);
    }

    /**
     * Validate the form
     * @return boolean
     */
    function validate($callHooks = true) {
        $valid = parent::validate($callHooks);
        
        if ($valid) {
            // Test S3 connection
            if (!$this->_testS3Connection()) {
                $this->addError('s3_connection', __('plugins.generic.s3Storage.settings.connectionTest.failed'));
                $valid = false;
            }
        }
        
        return $valid;
    }

    /**
     * Test S3 connection with provided credentials
     * @return boolean
     */
    private function _testS3Connection() {
        try {
            require_once('lib/aws/aws-autoloader.php');
            use Aws\S3\S3Client;
            
            $provider = $this->getData('s3_provider');
            $customEndpoint = $this->getData('s3_custom_endpoint');
            $region = $this->getData('s3_region');
            
            $config = [
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $this->getData('s3_key'),
                    'secret' => $this->getData('s3_secret'),
                ],
            ];

            // Set endpoint based on provider
            switch ($provider) {
                case 'wasabi':
                    $config['endpoint'] = $customEndpoint ?: "https://s3.{$region}.wasabisys.com";
                    break;
                case 'digitalocean':
                    $config['endpoint'] = $customEndpoint ?: "https://{$region}.digitaloceanspaces.com";
                    break;
                case 'custom':
                    if ($customEndpoint) {
                        $config['endpoint'] = $customEndpoint;
                    }
                    break;
                case 'aws':
                default:
                    if ($customEndpoint) {
                        $config['endpoint'] = $customEndpoint;
                    }
                    break;
            }
            
            $s3Client = new S3Client($config);
            
            // Test bucket access
            $result = $s3Client->headBucket([
                'Bucket' => $this->getData('s3_bucket'),
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log('S3StoragePlugin: Connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available S3-compatible providers
     * @return array
     */
    private function _getS3Providers() {
        return array(
            'aws' => __('plugins.generic.s3Storage.provider.aws'),
            'wasabi' => __('plugins.generic.s3Storage.provider.wasabi'),
            'digitalocean' => __('plugins.generic.s3Storage.provider.digitalocean'),
            'custom' => __('plugins.generic.s3Storage.provider.custom'),
        );
    }

    /**
     * Get available regions for different providers
     * @return array
     */
    private function _getS3Regions() {
        return array(
            // AWS regions
            'us-east-1' => 'US East (N. Virginia)',
            'us-east-2' => 'US East (Ohio)',
            'us-west-1' => 'US West (N. California)',
            'us-west-2' => 'US West (Oregon)',
            'ca-central-1' => 'Canada (Central)',
            'eu-central-1' => 'Europe (Frankfurt)',
            'eu-west-1' => 'Europe (Ireland)',
            'eu-west-2' => 'Europe (London)',
            'eu-west-3' => 'Europe (Paris)',
            'eu-north-1' => 'Europe (Stockholm)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            'ap-northeast-2' => 'Asia Pacific (Seoul)',
            'ap-northeast-3' => 'Asia Pacific (Osaka)',
            'ap-southeast-1' => 'Asia Pacific (Singapore)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)',
            'ap-south-1' => 'Asia Pacific (Mumbai)',
            'sa-east-1' => 'South America (São Paulo)',
            // Wasabi regions
            'us-east-1' => 'Wasabi US East 1 (N. Virginia)',
            'us-east-2' => 'Wasabi US East 2 (N. Virginia)',
            'us-central-1' => 'Wasabi US Central 1 (Texas)',
            'us-west-1' => 'Wasabi US West 1 (Oregon)',
            'eu-central-1' => 'Wasabi EU Central 1 (Amsterdam)',
            'ap-northeast-1' => 'Wasabi AP Northeast 1 (Tokyo)',
            // DigitalOcean regions
            'nyc3' => 'DigitalOcean NYC3',
            'sfo3' => 'DigitalOcean SFO3',
            'sgp1' => 'DigitalOcean SGP1',
            'fra1' => 'DigitalOcean FRA1',
            'ams3' => 'DigitalOcean AMS3',
        );
    }

    /**
     * Get cron frequency options
     * @return array
     */
    private function _getCronFrequencies() {
        return array(
            'hourly' => __('plugins.generic.s3Storage.cron.frequency.hourly'),
            'daily' => __('plugins.generic.s3Storage.cron.frequency.daily'),
            'weekly' => __('plugins.generic.s3Storage.cron.frequency.weekly'),
            'monthly' => __('plugins.generic.s3Storage.cron.frequency.monthly'),
        );
    }
} 