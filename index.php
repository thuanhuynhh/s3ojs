<?php

/**
 * @file plugins/generic/s3Storage/index.php
 *
 * Copyright (c) 2023 OJS/PKP
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_s3Storage
 * @brief Wrapper for S3 Storage plugin.
 *
 */

require_once('S3StoragePlugin.inc.php');

return new S3StoragePlugin(); 