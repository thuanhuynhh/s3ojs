<?php

/**
 * @file plugins/generic/s3Storage/S3StorageCronHandler.inc.php
 *
 * Copyright (c) 2023 OJS/PKP
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class S3StorageCronHandler
 * @ingroup plugins_generic_s3Storage
 *
 * @brief Cron handler for S3 Storage plugin maintenance tasks
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');

class S3StorageCronHandler extends ScheduledTask {

    /** @var S3StoragePlugin */
    private $plugin;

    /**
     * Constructor
     * @param array $args task arguments
     */
    function __construct($args) {
        parent::__construct($args);
        
        // Get plugin instance
        $pluginRegistry = PluginRegistry::getPluginRegistry();
        $this->plugin = $pluginRegistry->getPlugin('generic', 's3Storage');
    }

    /**
     * Execute scheduled task
     * @return boolean Success/failure
     */
    function executeActions() {
        if (!$this->plugin || !$this->plugin->getEnabled()) {
            return false;
        }

        $this->addExecutionLogEntry('S3 Storage maintenance started', SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll();
        
        $totalSynced = 0;
        $totalCleaned = 0;
        $errors = [];

        while ($context = $contexts->next()) {
            if ($this->plugin->getEnabled($context->getId())) {
                try {
                    // Run sync if enabled
                    if ($this->plugin->getSetting($context->getId(), 's3_auto_sync')) {
                        $syncResults = $this->performSync($context);
                        $totalSynced += $syncResults['success'];
                        if (!empty($syncResults['errors'])) {
                            $errors = array_merge($errors, $syncResults['errors']);
                        }
                    }

                    // Run cleanup if enabled
                    if ($this->plugin->getSetting($context->getId(), 's3_cleanup_orphaned')) {
                        $cleanupResults = $this->performCleanup($context);
                        $totalCleaned += $cleanupResults['deleted'];
                        if (!empty($cleanupResults['errors'])) {
                            $errors = array_merge($errors, $cleanupResults['errors']);
                        }
                    }

                    // Run storage health check
                    $this->performHealthCheck($context);

                } catch (Exception $e) {
                    $errors[] = "Context {$context->getId()}: " . $e->getMessage();
                    $this->addExecutionLogEntry($e->getMessage(), SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
                }
            }
        }

        // Log results
        if ($totalSynced > 0) {
            $this->addExecutionLogEntry("Synced {$totalSynced} files to cloud storage", SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
        }

        if ($totalCleaned > 0) {
            $this->addExecutionLogEntry("Cleaned {$totalCleaned} orphaned files", SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addExecutionLogEntry($error, SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            }
        }

        $this->addExecutionLogEntry('S3 Storage maintenance completed', SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        return empty($errors);
    }

    /**
     * Perform file sync for a context
     * @param Context $context
     * @return array Sync results
     */
    private function performSync($context) {
        $fileManager = $this->getS3FileManager($context->getId());
        
        if (!$fileManager) {
            return ['success' => 0, 'errors' => ['File manager not available']];
        }

        $filesDir = Config::getVar('files', 'files_dir') . '/journals/' . $context->getId();
        
        if (!is_dir($filesDir)) {
            return ['success' => 0, 'errors' => ['Files directory not found']];
        }

        return $fileManager->syncToCloud($filesDir);
    }

    /**
     * Perform cleanup for a context
     * @param Context $context
     * @return array Cleanup results
     */
    private function performCleanup($context) {
        $fileManager = $this->getS3FileManager($context->getId());
        
        if (!$fileManager) {
            return ['deleted' => 0, 'errors' => ['File manager not available']];
        }

        // Get valid files from database
        $validFiles = $this->getValidFilesFromDatabase($context);
        
        return $fileManager->cleanupOrphanedFiles($validFiles);
    }

    /**
     * Perform storage health check
     * @param Context $context
     */
    private function performHealthCheck($context) {
        $fileManager = $this->getS3FileManager($context->getId());
        
        if (!$fileManager) {
            $this->addExecutionLogEntry("Context {$context->getId()}: Storage not configured", SCHEDULED_TASK_MESSAGE_TYPE_WARNING);
            return;
        }

        // Test connection
        if (!$fileManager->testConnection()) {
            $this->addExecutionLogEntry("Context {$context->getId()}: Storage connection failed", SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
            return;
        }

        // Get storage stats
        $stats = $fileManager->getStorageStats();
        $this->addExecutionLogEntry("Context {$context->getId()}: {$stats['cloud']['count']} files, " . $this->formatBytes($stats['cloud']['size']) . " used", SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);

        // Check hybrid mode sync status
        if ($this->plugin->getSetting($context->getId(), 's3_hybrid_mode')) {
            $this->checkHybridModeSync($context, $fileManager);
        }
    }

    /**
     * Check hybrid mode synchronization status
     * @param Context $context
     * @param S3FileManager $fileManager
     */
    private function checkHybridModeSync($context, $fileManager) {
        // This would implement checks to ensure local and cloud storage are in sync
        // For now, just log that hybrid mode is active
        $this->addExecutionLogEntry("Context {$context->getId()}: Hybrid mode active", SCHEDULED_TASK_MESSAGE_TYPE_NOTICE);
    }

    /**
     * Get S3 file manager instance for a context
     * @param int $contextId
     * @return S3FileManager|null
     */
    private function getS3FileManager($contextId) {
        $bucket = $this->plugin->getSetting($contextId, 's3_bucket');
        $key = $this->plugin->getSetting($contextId, 's3_key');
        $secret = $this->plugin->getSetting($contextId, 's3_secret');
        $region = $this->plugin->getSetting($contextId, 's3_region');
        $provider = $this->plugin->getSetting($contextId, 's3_provider') ?: 'aws';
        $customEndpoint = $this->plugin->getSetting($contextId, 's3_custom_endpoint');
        $hybridMode = $this->plugin->getSetting($contextId, 's3_hybrid_mode');
        $fallbackEnabled = $this->plugin->getSetting($contextId, 's3_fallback_enabled');
        
        if (!$bucket || !$key || !$secret || !$region) {
            return null;
        }
        
        import('plugins.generic.s3Storage.S3FileManager');
        return new S3FileManager($bucket, $key, $secret, $region, $provider, $customEndpoint, $hybridMode, $fallbackEnabled);
    }

    /**
     * Get valid files from database for a context
     * @param Context $context
     * @return array List of valid file paths
     */
    private function getValidFilesFromDatabase($context) {
        $validFiles = array();
        
        // Get submission files
        $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
        $submissionFiles = $submissionFileDao->getByContextId($context->getId());
        
        while ($submissionFile = $submissionFiles->next()) {
            if ($submissionFile->getData('path')) {
                $validFiles[] = $submissionFile->getData('path');
            }
        }

        // Get galley files
        $galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
        $galleys = $galleyDao->getByContextId($context->getId());
        
        while ($galley = $galleys->next()) {
            if ($galley->getFile() && $galley->getFile()->getData('path')) {
                $validFiles[] = $galley->getFile()->getData('path');
            }
        }

        // Get issue files (covers, etc.)
        $issueDao = DAORegistry::getDAO('IssueDAO');
        $issues = $issueDao->getByJournalId($context->getId());
        
        while ($issue = $issues->next()) {
            $coverImagePath = $issue->getCoverImage();
            if ($coverImagePath) {
                $validFiles[] = $coverImagePath;
            }
        }

        return array_unique($validFiles);
    }

    /**
     * Format bytes to human readable format
     * @param int $bytes
     * @return string Formatted size
     */
    private function formatBytes($bytes) {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    /**
     * Check if maintenance should run based on frequency setting
     * @param string $frequency
     * @return boolean
     */
    public function shouldRunMaintenance($frequency) {
        $lastRun = $this->plugin->getSetting(CONTEXT_ID_NONE, 's3_last_maintenance_run');
        $now = time();
        
        if (!$lastRun) {
            return true;
        }
        
        switch ($frequency) {
            case 'hourly':
                return ($now - $lastRun) >= 3600; // 1 hour
            case 'daily':
                return ($now - $lastRun) >= 86400; // 24 hours
            case 'weekly':
                return ($now - $lastRun) >= 604800; // 7 days
            case 'monthly':
                return ($now - $lastRun) >= 2592000; // 30 days
            default:
                return false;
        }
    }

    /**
     * Update last maintenance run timestamp
     */
    public function updateLastMaintenanceRun() {
        $this->plugin->updateSetting(CONTEXT_ID_NONE, 's3_last_maintenance_run', time(), 'int');
    }
} 