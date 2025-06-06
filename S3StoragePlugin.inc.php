<?php

/**
 * @file plugins/generic/s3Storage/S3StoragePlugin.inc.php
 *
 * Copyright (c) 2023 OJS/PKP
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class S3StoragePlugin
 * @ingroup plugins_generic_s3Storage
 *
 * @brief S3-compatible Storage plugin for OJS 3.4.0 with hybrid mode and fallback support
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class S3StoragePlugin extends GenericPlugin {
    
    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
        
        if ($success && $this->getEnabled($mainContextId)) {
            // Register the S3 file manager
            HookRegistry::register('FileManager::getFileManager', array($this, 'getFileManager'));
            
            // Add S3 settings to the file settings form
            HookRegistry::register('Form::config::before', array($this, 'addS3Settings'));
            
            // Register scheduled tasks hook
            HookRegistry::register('Scheduler::execute', array($this, 'scheduledTasks'));
            
            // Auto-sync hook (if enabled)
            if ($this->getSetting($mainContextId, 's3_auto_sync')) {
                HookRegistry::register('FileManager::uploadFile', array($this, 'autoSyncFile'));
            }
        }
        return $success;
    }

    /**
     * Get the display name of this plugin.
     * @return String
     */
    public function getDisplayName() {
        return __('plugins.generic.s3Storage.displayName');
    }

    /**
     * Get a description of the plugin.
     * @return String
     */
    public function getDescription() {
        return __('plugins.generic.s3Storage.description');
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs) {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }
        
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        $actions[] = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );
        
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request) {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON,  LOCALE_COMPONENT_PKP_MANAGER);
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->registerPlugin('function', 'plugin_url', array($this, 'smartyPluginUrl'));

                $this->import('S3StorageSettingsForm');
                $form = new S3StorageSettingsForm($this, $context->getId());

                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
                
            case 'sync':
                return $this->handleSync($request);
                
            case 'cleanup':
                return $this->handleCleanup($request);
                
            case 'testConnection':
                return $this->handleTestConnection($request);
                
            case 'stats':
                return $this->handleStats($request);
        }
        return parent::manage($args, $request);
    }

    /**
     * Handle media library sync
     * @param PKPRequest $request
     * @return JSONMessage
     */
    private function handleSync($request) {
        $context = $request->getContext();
        $fileManager = $this->getS3FileManager($context->getId());
        
        if (!$fileManager) {
            return new JSONMessage(false, __('plugins.generic.s3Storage.sync.failed'));
        }
        
        // Get local files directory
        $filesDir = Config::getVar('files', 'files_dir') . '/journals/' . $context->getId();
        
        if (!is_dir($filesDir)) {
            return new JSONMessage(false, __('plugins.generic.s3Storage.sync.failed'));
        }
        
        // Perform sync
        $results = $fileManager->syncToCloud($filesDir);
        
        if ($results['failed'] > 0) {
            return new JSONMessage(false, __('plugins.generic.s3Storage.sync.failed') . ': ' . implode(', ', $results['errors']));
        }
        
        return new JSONMessage(true, __('plugins.generic.s3Storage.sync.completed') . " ({$results['success']} files synced)");
    }

    /**
     * Handle storage cleanup
     * @param PKPRequest $request
     * @return JSONMessage
     */
    private function handleCleanup($request) {
        $context = $request->getContext();
        $fileManager = $this->getS3FileManager($context->getId());
        
        if (!$fileManager) {
            return new JSONMessage(false, __('plugins.generic.s3Storage.cleanup.failed'));
        }
        
        // Get valid files from database (implement based on OJS structure)
        $validFiles = $this->getValidFilesFromDatabase($context);
        
        // Perform cleanup
        $results = $fileManager->cleanupOrphanedFiles($validFiles);
        
        if (!empty($results['errors'])) {
            return new JSONMessage(false, __('plugins.generic.s3Storage.cleanup.failed') . ': ' . implode(', ', $results['errors']));
        }
        
        return new JSONMessage(true, __('plugins.generic.s3Storage.cleanup.completed') . " ({$results['deleted']} files cleaned)");
    }

    /**
     * Handle connection test
     * @param PKPRequest $request
     * @return JSONMessage
     */
    private function handleTestConnection($request) {
        $contextId = $request->getContext()->getId();
        
        // Get settings from request or stored settings
        $bucket = $request->getUserVar('s3_bucket') ?: $this->getSetting($contextId, 's3_bucket');
        $key = $request->getUserVar('s3_key') ?: $this->getSetting($contextId, 's3_key');
        $secret = $request->getUserVar('s3_secret') ?: $this->getSetting($contextId, 's3_secret');
        $region = $request->getUserVar('s3_region') ?: $this->getSetting($contextId, 's3_region');
        $provider = $request->getUserVar('s3_provider') ?: $this->getSetting($contextId, 's3_provider');
        $customEndpoint = $request->getUserVar('s3_custom_endpoint') ?: $this->getSetting($contextId, 's3_custom_endpoint');
        
        try {
            $this->import('S3FileManager');
            $fileManager = new S3FileManager($bucket, $key, $secret, $region, $provider, $customEndpoint, false, false);
            
            if ($fileManager->testConnection()) {
                return new JSONMessage(true, array('status' => true));
            } else {
                return new JSONMessage(true, array('status' => false));
            }
        } catch (Exception $e) {
            error_log('S3StoragePlugin: Connection test failed: ' . $e->getMessage());
            return new JSONMessage(true, array('status' => false));
        }
    }

    /**
     * Handle storage statistics
     * @param PKPRequest $request
     * @return JSONMessage
     */
    private function handleStats($request) {
        $context = $request->getContext();
        $fileManager = $this->getS3FileManager($context->getId());
        
        if (!$fileManager) {
            return new JSONMessage(false, 'Storage not configured');
        }
        
        $stats = $fileManager->getStorageStats();
        return new JSONMessage(true, $stats);
    }

    /**
     * Hook callback: register the S3 file manager
     * @param $hookName string
     * @param $args array
     */
    public function getFileManager($hookName, $args) {
        if ($this->getEnabled()) {
            $fileManager = $this->getS3FileManager();
            if ($fileManager) {
                $args[0] = $fileManager;
                return true;
            }
        }
        return false;
    }

    /**
     * Get S3 file manager instance
     * @param int $contextId Context ID (optional)
     * @return S3FileManager|null
     */
    private function getS3FileManager($contextId = null) {
        if ($contextId === null) {
            $contextId = CONTEXT_ID_NONE;
        }
        
        $bucket = $this->getSetting($contextId, 's3_bucket');
        $key = $this->getSetting($contextId, 's3_key');
        $secret = $this->getSetting($contextId, 's3_secret');
        $region = $this->getSetting($contextId, 's3_region');
        $provider = $this->getSetting($contextId, 's3_provider') ?: 'aws';
        $customEndpoint = $this->getSetting($contextId, 's3_custom_endpoint');
        $hybridMode = $this->getSetting($contextId, 's3_hybrid_mode');
        $fallbackEnabled = $this->getSetting($contextId, 's3_fallback_enabled');
        
        if (!$bucket || !$key || !$secret || !$region) {
            return null;
        }
        
        $this->import('S3FileManager');
        return new S3FileManager($bucket, $key, $secret, $region, $provider, $customEndpoint, $hybridMode, $fallbackEnabled);
    }

    /**
     * Hook callback: Auto-sync files when uploaded
     * @param $hookName string
     * @param $args array
     */
    public function autoSyncFile($hookName, $args) {
        $filePath = $args[0];
        $fileManager = $this->getS3FileManager();
        
        if ($fileManager && file_exists($filePath)) {
            $fileManager->uploadFile($filePath, basename($filePath));
        }
    }

    /**
     * Hook callback: Handle scheduled tasks
     * @param $hookName string
     * @param $args array
     */
    public function scheduledTasks($hookName, $args) {
        $taskName = $args[0];
        
        if ($taskName === 's3_storage_maintenance') {
            $this->runMaintenanceTasks();
        }
    }

    /**
     * Run maintenance tasks (cleanup, sync, etc.)
     */
    private function runMaintenanceTasks() {
        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll();
        
        while ($context = $contexts->next()) {
            if ($this->getEnabled($context->getId())) {
                $fileManager = $this->getS3FileManager($context->getId());
                
                if ($fileManager && $this->getSetting($context->getId(), 's3_cleanup_orphaned')) {
                    $validFiles = $this->getValidFilesFromDatabase($context);
                    $fileManager->cleanupOrphanedFiles($validFiles);
                }
                
                if ($fileManager && $this->getSetting($context->getId(), 's3_auto_sync')) {
                    $filesDir = Config::getVar('files', 'files_dir') . '/journals/' . $context->getId();
                    if (is_dir($filesDir)) {
                        $fileManager->syncToCloud($filesDir);
                    }
                }
            }
        }
    }

    /**
     * Get valid files from database
     * @param Context $context
     * @return array List of valid file paths
     */
    private function getValidFilesFromDatabase($context) {
        $validFiles = array();
        
        // This would need to be implemented based on OJS database structure
        // Get files from submission_files, galley_files, etc.
        $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
        $submissionFiles = $submissionFileDao->getByContextId($context->getId());
        
        while ($submissionFile = $submissionFiles->next()) {
            if ($submissionFile->getData('path')) {
                $validFiles[] = $submissionFile->getData('path');
            }
        }
        
        return $validFiles;
    }

    /**
     * Add S3 settings to the form config
     * @param $hookName string
     * @param $form FormComponent
     */
    public function addS3Settings($hookName, $form) {
        if ($form->id !== FORM_FILE_SETTINGS) {
            return;
        }

        $context = Application::get()->getRequest()->getContext();
        if (!$context || !$this->getEnabled($context->getId())) {
            return;
        }

        $form->addGroup([
            'id' => 's3_settings',
            'label' => __('plugins.generic.s3Storage.settings.title'),
            'description' => __('plugins.generic.s3Storage.settings.description'),
        ])
        ->addField(new \PKP\components\forms\FieldSelect('s3_provider', [
            'label' => __('plugins.generic.s3Storage.settings.provider'),
            'description' => __('plugins.generic.s3Storage.settings.provider.description'),
            'value' => $this->getSetting($context->getId(), 's3_provider'),
            'groupId' => 's3_settings',
            'options' => [
                ['value' => 'aws', 'label' => __('plugins.generic.s3Storage.provider.aws')],
                ['value' => 'wasabi', 'label' => __('plugins.generic.s3Storage.provider.wasabi')],
                ['value' => 'digitalocean', 'label' => __('plugins.generic.s3Storage.provider.digitalocean')],
                ['value' => 'custom', 'label' => __('plugins.generic.s3Storage.provider.custom')],
            ],
        ]))
        ->addField(new \PKP\components\forms\FieldText('s3_custom_endpoint', [
            'label' => __('plugins.generic.s3Storage.settings.customEndpoint'),
            'description' => __('plugins.generic.s3Storage.settings.customEndpoint.description'),
            'value' => $this->getSetting($context->getId(), 's3_custom_endpoint'),
            'groupId' => 's3_settings',
        ]))
        ->addField(new \PKP\components\forms\FieldText('s3_bucket', [
            'label' => __('plugins.generic.s3Storage.settings.bucket'),
            'description' => __('plugins.generic.s3Storage.settings.bucket.description'),
            'value' => $this->getSetting($context->getId(), 's3_bucket'),
            'groupId' => 's3_settings',
        ]))
        ->addField(new \PKP\components\forms\FieldText('s3_key', [
            'label' => __('plugins.generic.s3Storage.settings.key'),
            'description' => __('plugins.generic.s3Storage.settings.key.description'),
            'value' => $this->getSetting($context->getId(), 's3_key'),
            'groupId' => 's3_settings',
        ]))
        ->addField(new \PKP\components\forms\FieldText('s3_secret', [
            'label' => __('plugins.generic.s3Storage.settings.secret'),
            'description' => __('plugins.generic.s3Storage.settings.secret.description'),
            'value' => $this->getSetting($context->getId(), 's3_secret'),
            'groupId' => 's3_settings',
            'inputType' => 'password',
        ]))
        ->addField(new \PKP\components\forms\FieldSelect('s3_region', [
            'label' => __('plugins.generic.s3Storage.settings.region'),
            'description' => __('plugins.generic.s3Storage.settings.region.description'),
            'value' => $this->getSetting($context->getId(), 's3_region'),
            'groupId' => 's3_settings',
            'options' => $this->getRegionOptions(),
        ]))
        ->addField(new \PKP\components\forms\FieldOptions('s3_hybrid_mode', [
            'label' => __('plugins.generic.s3Storage.settings.hybridMode'),
            'description' => __('plugins.generic.s3Storage.settings.hybridMode.description'),
            'value' => $this->getSetting($context->getId(), 's3_hybrid_mode'),
            'groupId' => 's3_settings',
            'type' => 'checkbox',
        ]))
        ->addField(new \PKP\components\forms\FieldOptions('s3_fallback_enabled', [
            'label' => __('plugins.generic.s3Storage.settings.fallbackEnabled'),
            'description' => __('plugins.generic.s3Storage.settings.fallbackEnabled.description'),
            'value' => $this->getSetting($context->getId(), 's3_fallback_enabled'),
            'groupId' => 's3_settings',
            'type' => 'checkbox',
        ]))
        ->addField(new \PKP\components\forms\FieldOptions('s3_auto_sync', [
            'label' => __('plugins.generic.s3Storage.settings.autoSync'),
            'description' => __('plugins.generic.s3Storage.settings.autoSync.description'),
            'value' => $this->getSetting($context->getId(), 's3_auto_sync'),
            'groupId' => 's3_settings',
            'type' => 'checkbox',
        ]))
        ->addField(new \PKP\components\forms\FieldOptions('s3_cron_enabled', [
            'label' => __('plugins.generic.s3Storage.settings.cronEnabled'),
            'description' => __('plugins.generic.s3Storage.settings.cronEnabled.description'),
            'value' => $this->getSetting($context->getId(), 's3_cron_enabled'),
            'groupId' => 's3_settings',
            'type' => 'checkbox',
        ]))
        ->addField(new \PKP\components\forms\FieldSelect('s3_cron_frequency', [
            'label' => __('plugins.generic.s3Storage.settings.cronFrequency'),
            'description' => __('plugins.generic.s3Storage.settings.cronFrequency.description'),
            'value' => $this->getSetting($context->getId(), 's3_cron_frequency'),
            'groupId' => 's3_settings',
            'options' => [
                ['value' => 'hourly', 'label' => __('plugins.generic.s3Storage.cron.frequency.hourly')],
                ['value' => 'daily', 'label' => __('plugins.generic.s3Storage.cron.frequency.daily')],
                ['value' => 'weekly', 'label' => __('plugins.generic.s3Storage.cron.frequency.weekly')],
                ['value' => 'monthly', 'label' => __('plugins.generic.s3Storage.cron.frequency.monthly')],
            ],
        ]))
        ->addField(new \PKP\components\forms\FieldOptions('s3_cleanup_orphaned', [
            'label' => __('plugins.generic.s3Storage.settings.cleanupOrphaned'),
            'description' => __('plugins.generic.s3Storage.settings.cleanupOrphaned.description'),
            'value' => $this->getSetting($context->getId(), 's3_cleanup_orphaned'),
            'groupId' => 's3_settings',
            'type' => 'checkbox',
        ]));
    }

    /**
     * Get region options for different providers
     * @return array
     */
    private function getRegionOptions() {
        return [
            // AWS regions
            ['value' => 'us-east-1', 'label' => 'US East (N. Virginia)'],
            ['value' => 'us-east-2', 'label' => 'US East (Ohio)'],
            ['value' => 'us-west-1', 'label' => 'US West (N. California)'],
            ['value' => 'us-west-2', 'label' => 'US West (Oregon)'],
            ['value' => 'ca-central-1', 'label' => 'Canada (Central)'],
            ['value' => 'eu-central-1', 'label' => 'Europe (Frankfurt)'],
            ['value' => 'eu-west-1', 'label' => 'Europe (Ireland)'],
            ['value' => 'eu-west-2', 'label' => 'Europe (London)'],
            ['value' => 'eu-west-3', 'label' => 'Europe (Paris)'],
            ['value' => 'eu-north-1', 'label' => 'Europe (Stockholm)'],
            ['value' => 'ap-northeast-1', 'label' => 'Asia Pacific (Tokyo)'],
            ['value' => 'ap-northeast-2', 'label' => 'Asia Pacific (Seoul)'],
            ['value' => 'ap-northeast-3', 'label' => 'Asia Pacific (Osaka)'],
            ['value' => 'ap-southeast-1', 'label' => 'Asia Pacific (Singapore)'],
            ['value' => 'ap-southeast-2', 'label' => 'Asia Pacific (Sydney)'],
            ['value' => 'ap-south-1', 'label' => 'Asia Pacific (Mumbai)'],
            ['value' => 'sa-east-1', 'label' => 'South America (São Paulo)'],
            // Wasabi regions
            ['value' => 'us-east-1', 'label' => 'Wasabi US East 1 (N. Virginia)'],
            ['value' => 'us-east-2', 'label' => 'Wasabi US East 2 (N. Virginia)'],
            ['value' => 'us-central-1', 'label' => 'Wasabi US Central 1 (Texas)'],
            ['value' => 'us-west-1', 'label' => 'Wasabi US West 1 (Oregon)'],
            ['value' => 'eu-central-1', 'label' => 'Wasabi EU Central 1 (Amsterdam)'],
            ['value' => 'ap-northeast-1', 'label' => 'Wasabi AP Northeast 1 (Tokyo)'],
            // DigitalOcean regions
            ['value' => 'nyc3', 'label' => 'DigitalOcean NYC3'],
            ['value' => 'sfo3', 'label' => 'DigitalOcean SFO3'],
            ['value' => 'sgp1', 'label' => 'DigitalOcean SGP1'],
            ['value' => 'fra1', 'label' => 'DigitalOcean FRA1'],
            ['value' => 'ams3', 'label' => 'DigitalOcean AMS3'],
        ];
    }

    /**
     * @copydoc Plugin::getInstallEmailTemplatesFile()
     */
    public function getInstallEmailTemplatesFile() {
        return ($this->getPluginPath() . '/emailTemplates.xml');
    }

    /**
     * @copydoc Plugin::getInstallEmailTemplateDataFile()
     */
    public function getInstallEmailTemplateDataFile() {
        return ($this->getPluginPath() . '/locale/{$installedLocale}/emailTemplates.xml');
    }
} 