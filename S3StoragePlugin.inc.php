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
require_once(dirname(__FILE__) . '/vendor/aws/aws-autoloader.php');

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
        $validFiles = [];
        $contextId = $context->getId();

        // Submission files (includes galleys, artwork, etc.)
        $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
        /** @var DAOResultFactory $submissionFiles */
        $submissionFiles = $submissionFileDao->getByContextId($contextId);
        while ($submissionFile = $submissionFiles->next()) {
            /** @var SubmissionFile $submissionFile */
            $path = $submissionFile->getData('path');
            if ($path) {
                $validFiles[] = $path;
            }
        }

        return array_unique($validFiles);
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
} 