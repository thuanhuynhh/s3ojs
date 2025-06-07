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
import('lib.pkp.classes.core.JSONMessage');
import('classes.template.TemplateManager');
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.request.AjaxModal');
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
     * Get the name of this plugin.
     * @return String
     */
    public function getName() {
        return 's3ojs';
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
        error_log('S3StoragePlugin: manage() called with verb: ' . $request->getUserVar('verb'));
        
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                import('classes.i18n.AppLocale');
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
                    // If validation failed, return form with errors
                    return new JSONMessage(true, $form->fetch($request));
                } else {
                    $form->initData();
                    return new JSONMessage(true, $form->fetch($request));
                }
                
            case 'sync':
                return $this->handleSync($request);
                
            case 'cleanup':
                return $this->handleCleanup($request);
                
            case 'testConnection':
                error_log('S3StoragePlugin: testConnection case reached');
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
        $this->import('S3FileManager');
        $fileManager = $this->getS3FileManager($context->getId());
        
        if (!$fileManager) {
            $errorMessage = 'Sync failed: S3FileManager could not be initialized. Check settings.';
            error_log('S3StoragePlugin: ' . $errorMessage);
            return new JSONMessage(false, $errorMessage);
        }
        
        // Get local files directory
        $filesDir = Config::getVar('files', 'files_dir') . '/journals/' . $context->getId();
        error_log('S3StoragePlugin: Starting sync for directory: ' . $filesDir);

        if (!is_dir($filesDir)) {
            $errorMessage = 'Sync failed: Local files directory does not exist: ' . $filesDir;
            error_log('S3StoragePlugin: ' . $errorMessage);
            return new JSONMessage(false, $errorMessage);
        }
        
        // Perform sync
        $results = $fileManager->syncToCloud($filesDir);
        
        if ($results['failed'] > 0) {
            $errorMessage = __('plugins.generic.s3Storage.sync.failed') . ': ' . implode(', ', $results['errors']);
            error_log('S3StoragePlugin: Sync process reported failures. Details: ' . json_encode($results));
            return new JSONMessage(false, $errorMessage);
        }
        
        $successMessage = __('plugins.generic.s3Storage.sync.completed') . " ({$results['success']} files synced)";
        error_log('S3StoragePlugin: Sync completed successfully. Details: ' . json_encode($results));
        return new JSONMessage(true, $successMessage);
    }

    /**
     * Handle storage cleanup
     * @param PKPRequest $request
     * @return JSONMessage
     */
    private function handleCleanup($request) {
        $context = $request->getContext();
        $this->import('S3FileManager');
        $fileManager = $this->getS3FileManager($context->getId());
        
        if (!$fileManager) {
            $errorMessage = 'Cleanup failed: S3FileManager could not be initialized. Check settings.';
            error_log('S3StoragePlugin: ' . $errorMessage);
            return new JSONMessage(false, $errorMessage);
        }
        
        // Get valid files from database
        error_log('S3StoragePlugin: Starting cleanup. Fetching valid file list from database.');
        $validFiles = $this->getValidFilesFromDatabase($context);
        error_log('S3StoragePlugin: Found ' . count($validFiles) . ' valid files in the database.');
        
        // Perform cleanup
        $results = $fileManager->cleanupOrphanedFiles($validFiles);
        
        if (!empty($results['errors'])) {
            $errorMessage = __('plugins.generic.s3Storage.cleanup.failed') . ': ' . implode(', ', $results['errors']);
            error_log('S3StoragePlugin: Cleanup process reported failures. Details: ' . json_encode($results));
            return new JSONMessage(false, $errorMessage);
        }
        
        $successMessage = __('plugins.generic.s3Storage.cleanup.completed') . " ({$results['deleted']} files cleaned)";
        error_log('S3StoragePlugin: Cleanup completed successfully. Details: ' . json_encode($results));
        return new JSONMessage(true, $successMessage);
    }

    /**
     * Handle connection test
     * @param PKPRequest $request
     * @return JSONMessage
     */
    private function handleTestConnection($request) {
        $contextId = $request->getContext()->getId();
        
        // Get settings from the form submission for the test
        $bucket = $request->getUserVar('s3_bucket');
        $key = $request->getUserVar('s3_key');
        $secret = $request->getUserVar('s3_secret');
        $region = $request->getUserVar('s3_region');
        $provider = $request->getUserVar('s3_provider');
        $customEndpoint = $request->getUserVar('s3_custom_endpoint');
        
        // For custom provider, if region is not provided, use a default one
        // as it's required by the AWS SDK.
        if ($provider === 'custom' && empty($region)) {
            $region = 'us-east-1'; // A common default
        }

        error_log('S3StoragePlugin: Testing connection with params - Provider: ' . $provider . ', Region: ' . $region . ', Endpoint: ' . $customEndpoint);

        try {
            $this->import('S3FileManager');
            $fileManager = new S3FileManager($bucket, $key, $secret, $region, $provider, $customEndpoint, false, false);
            
            $connectionResult = $fileManager->testConnection();

            if ($connectionResult === true) {
                return new JSONMessage(true, ['status' => true, 'message' => __('plugins.generic.s3Storage.settings.connectionTest.success')]);
            } else {
                // Failure, $connectionResult contains the error message string
                error_log('S3StoragePlugin: handleTestConnection failed. Reason: ' . $connectionResult);
                return new JSONMessage(true, ['status' => false, 'message' => $connectionResult]);
            }
        } catch (Exception $e) {
            $errorMessage = 'Connection test threw a fatal exception: ' . $e->getMessage();
            error_log('S3StoragePlugin: ' . $errorMessage);
            return new JSONMessage(true, ['status' => false, 'message' => $errorMessage]);
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
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if (!$context) {
            // If we can't determine the context, we can't get the correct settings.
            return false;
        }
        $contextId = $context->getId();
        
        if ($this->getEnabled($contextId)) {
            $fileManager = $this->getS3FileManager($contextId);
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
        
        // Basic credentials check
        if (empty($bucket) || empty($key) || empty($secret)) {
            error_log('S3StoragePlugin: Cannot initialize S3FileManager - missing bucket, key, or secret.');
            return null;
        }

        // Region is required for all providers except 'custom'
        if ($provider !== 'custom' && empty($region)) {
            error_log('S3StoragePlugin: Cannot initialize S3FileManager - region is required for provider "' . $provider . '".');
            return null;
        }
        
        // For custom provider, if region is not provided, use a default one.
        if ($provider === 'custom' && empty($region)) {
            $region = 'us-east-1'; // A common default
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
        import('lib.pkp.classes.submission.SubmissionFileDAO');
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