# S3-Compatible Storage Plugin for OJS 3.4.0

## Description

This plugin integrates S3-compatible storage for Open Journal Systems (OJS) 3.4.0, supporting multiple providers including AWS S3, Wasabi, DigitalOcean Spaces, and other S3-compatible services. The plugin provides advanced cloud storage solutions with features like hybrid mode, fallback mechanisms, automatic synchronization, and intelligent file management.

## Features

### Core Features
- **S3-Compatible Integration**: Supports AWS S3, Wasabi, DigitalOcean Spaces, and custom endpoints.
- **Hybrid Mode**: Stores files both locally and on the cloud for redundancy.
- **Fallback Mechanism**: Automatically switches to local storage when cloud storage is unavailable.
- **Media Library Sync**: Synchronizes existing OJS files to the configured cloud storage bucket.

### Advanced Features
- **Scheduled Cron Jobs**: Schedules automated maintenance and cleanup tasks.
- **Efficient File Management**: Automatically and efficiently deletes orphaned and unused files, even from large buckets.
- **Customizable Settings**: A comprehensive and user-friendly settings interface with dynamic options.
- **Real-time Sync**: Optionally synchronizes files immediately upon upload.

### Multi-language Support
- Fully translated into English and Vietnamese.

### Security & Reliability
- SSL/HTTPS encryption for secure data transfer.
- Connection testing and health checks.
- Detailed access control management.

## System Requirements

- **OJS**: 3.4.0 or newer
- **PHP**: 7.3+ or 8.0+
- **PHP Extensions**: curl, openssl, json, fileinfo
- **Storage**: An S3-compatible bucket with appropriate access permissions.
- **Network**: An active internet connection to access the storage service.

## Installation

### 1. Download the Plugin and AWS SDK

1.  **Download the Plugin:** Download the latest release of the plugin from the project's **Releases** page and unzip it.
2.  **Download the AWS SDK for PHP:** Download the AWS SDK for PHP as a ZIP file from the [official AWS website](https://aws.amazon.com/sdk-for-php/).
3.  **Place the SDK inside the Plugin Directory:**
    *   Unzip the AWS SDK.
    *   Inside the plugin's main directory (`s3Storage`), create a new subdirectory named `vendor`.
    *   Copy the entire contents of the unzipped SDK into this `vendor/` directory.
    *   The final directory structure should look like this:
        ```
        s3Storage/
        ├── vendor/
        │   ├── aws-autoloader.php
        │   ├── Aws/
        │   ├── GuzzleHttp/
        │   └── ... (other SDK directories)
        ├── S3FileManager.inc.php
        ├── S3StoragePlugin.inc.php
        └── ... (other plugin files)
        ```

### 2. Upload the Plugin to OJS

1.  Compress the entire `s3Storage` directory (which now includes the `vendor` directory) into a single ZIP file.
2.  Log in to your OJS dashboard as an Administrator.
3.  Navigate to **Settings > Website > Plugins > Upload A New Plugin**.
4.  Upload the ZIP file you just created and follow the on-screen instructions.

### 3. Enable the Plugin
1.  Navigate to **Settings > Website > Plugins**.
2.  Find the "S3 Storage Plugin" in the Generic Plugins list.
3.  Click **Enable**.

### Installation via Command Line (CLI)
After placing the SDK in the correct location as described in Step 1:
```bash
# Install or upgrade the plugin
php lib/pkp/tools/installPluginVersion.php plugins/generic/s3Storage/version.xml

# Enable the plugin for a specific journal (replace a_journal_path with your journal's path)
php tools/plugin.php enable S3StoragePlugin a_journal_path
```

## Cron Job Configuration

For automated tasks (like cleanup and sync) to work, you need to configure a cron job on your server to execute the OJS scheduled tasks script.

**Cron Job Command:**
```bash
* * * * * php /path/to/your/ojs/tools/runScheduledTasks.php
```
*This command should be run frequently (e.g., every hour). The plugin will decide whether to execute its tasks based on your settings in the admin interface.*

In the plugin's settings page, you can enable or disable cron jobs and select which actions (cleanup, sync) should be performed.

## Troubleshooting

### Error: `The tar command is not available`
This error can occur when installing the plugin via the web interface if OJS cannot find the `tar` command on your server.
**Solution:**
1. Open the `config.inc.php` file in your OJS root directory.
2. Locate the `[cli]` section.
3. Provide the correct path to your `tar` executable. For example:
   ```ini
   tar = /bin/tar
   ```
For more details, see: [OJS Services](https://ojs-services.com/ojs-plugins/how-to-resolve-plugin-installation-error-in-ojs/)

### Compatibility issues when upgrading OJS
When you upgrade your OJS version, some plugins may become incompatible. Always check the Plugin Gallery and the plugin's documentation before upgrading.
**Solution:**
- Always back up your system before an upgrade.
- Upgrade plugins via the Plugin Gallery within OJS to ensure compatibility checks are performed.
- If installing manually, ensure you are using a plugin version that supports your OJS version.
For more details, see: [PKP Community Forum](http://forum.pkp.sfu.ca/t/commandline-upgrade-of-plugins/77385)

## Contributing

We welcome all contributions! Please see `CONTRIBUTING.md` for details on how to contribute, report bugs, and for our code standards.

## License

GNU General Public License v3.0

---

**Important Note:** This plugin requires a basic understanding of cloud storage and OJS administration. Always back up your data before installing or changing the configuration. For support, please create an issue on GitHub. 