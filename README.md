# S3-Compatible Storage Plugin for OJS 3.4.0

## Mô tả / Description

**Tiếng Việt:**
Plugin này tích hợp lưu trữ tương thích S3 cho Open Journal Systems (OJS) 3.4.0, hỗ trợ nhiều nhà cung cấp như AWS S3, Wasabi, DigitalOcean Spaces và các dịch vụ tương thích S3 khác. Plugin cung cấp giải pháp lưu trữ đám mây với các tính năng tiên tiến như chế độ lai (hybrid mode), cơ chế dự phòng (fallback), đồng bộ tự động và quản lý tệp tin thông minh.

**English:**
This plugin integrates S3-compatible storage for Open Journal Systems (OJS) 3.4.0, supporting multiple providers including AWS S3, Wasabi, DigitalOcean Spaces, and other S3-compatible services. The plugin provides advanced cloud storage solutions with features like hybrid mode, fallback mechanisms, automatic synchronization, and intelligent file management.

## Tính năng / Features

### 🔄 Core Features
- **S3-Compatible Integration**: Hỗ trợ AWS S3, Wasabi, DigitalOcean Spaces và custom endpoints
- **Hybrid Mode**: Lưu trữ files cả ở local và cloud để dự phòng
- **Fallback Mechanism**: Tự động chuyển sang local storage khi cloud không khả dụng
- **Media Library Sync**: Đồng bộ tự động files OJS tới cloud storage

### ⚡ Advanced Features
- **Custom Cron Jobs**: Lên lịch tác vụ bảo trì và dọn dẹp tự động
- **Efficient File Management**: Tự động xóa files mồ côi và không sử dụng
- **Customizable Settings**: Giao diện cấu hình đầy đủ và dễ sử dụng
- **Real-time Sync**: Đồng bộ files ngay khi upload (tùy chọn)

### 🌐 Multi-language Support
- Hỗ trợ đa ngôn ngữ (Tiếng Việt và Tiếng Anh)
- Giao diện admin hoàn toàn được dịch

### 🔒 Security & Reliability
- Mã hóa SSL/HTTPS cho truyền tải an toàn
- Kiểm tra kết nối và health checks
- Backup và phục hồi dữ liệu
- Quản lý quyền truy cập chi tiết

## Nhà cung cấp được hỗ trợ / Supported Providers

| Provider | Endpoint | Regions |
|----------|----------|---------|
| **Amazon S3** | Default AWS endpoints | Tất cả AWS regions |
| **Wasabi** | s3.{region}.wasabisys.com | US, EU, AP regions |
| **DigitalOcean Spaces** | {region}.digitaloceanspaces.com | NYC, SFO, SGP, FRA, AMS |
| **Custom S3-Compatible** | Custom endpoint | Configurable |

## Yêu cầu hệ thống / System Requirements

- **OJS**: 3.4.0 hoặc mới hơn
- **PHP**: 7.3+ hoặc 8.0+
- **Extensions**: curl, openssl, json, fileinfo
- **Storage**: S3-compatible bucket với quyền truy cập phù hợp
- **Network**: Internet connection để truy cập storage service

## Cài đặt / Installation

### Bước 1: Tải plugin / Download plugin

```bash
cd plugins/generic/
git clone https://github.com/your-repo/s3Storage.git
# hoặc tải và giải nén file ZIP vào thư mục plugins/generic/s3Storage/
```

### Bước 2: Cài đặt dependencies

```bash
cd plugins/generic/s3Storage/
composer install --no-dev
```

### Bước 3: Kích hoạt plugin / Enable plugin

1. Đăng nhập vào OJS với quyền quản trị
2. Vào **Settings > Website > Plugins**
3. Tìm "S3 Storage Plugin" trong danh sách Generic Plugins
4. Nhấp **Enable** để kích hoạt

## Cấu hình / Configuration

### AWS S3 Setup

1. **Tạo S3 Bucket:**
   ```bash
   aws s3 mb s3://my-ojs-files --region us-east-1
   ```

2. **Cấu hình CORS (nếu cần):**
   ```json
   [
     {
       "AllowedHeaders": ["*"],
       "AllowedMethods": ["GET", "PUT", "POST", "DELETE"],
       "AllowedOrigins": ["https://your-ojs-domain.com"],
       "ExposeHeaders": []
     }
   ]
   ```

3. **IAM Policy:**
   ```json
   {
       "Version": "2012-10-17",
       "Statement": [
           {
               "Effect": "Allow",
               "Action": [
                   "s3:GetObject",
                   "s3:PutObject",
                   "s3:DeleteObject",
                   "s3:ListBucket",
                   "s3:GetBucketLocation"
               ],
               "Resource": [
                   "arn:aws:s3:::my-ojs-files",
                   "arn:aws:s3:::my-ojs-files/*"
               ]
           }
       ]
   }
   ```

### Wasabi Setup

1. **Tạo Bucket:**
   - Truy cập Wasabi Console
   - Tạo bucket mới trong region mong muốn
   - Ghi nhớ bucket name và region

2. **Tạo Access Key:**
   - Vào Access Keys section
   - Tạo key pair mới
   - Lưu Access Key ID và Secret Key

### DigitalOcean Spaces Setup

1. **Tạo Space:**
   - Truy cập DigitalOcean Control Panel
   - Tạo Space mới
   - Chọn region và CDN settings

2. **Tạo API Key:**
   - Vào API > Spaces Keys
   - Generate key pair mới

### Plugin Configuration

1. **Vào Settings:**
   - **Settings > Website > Plugins**
   - Tìm "S3 Storage Plugin" và nhấp **Settings**

2. **Cấu hình cơ bản:**
   - **Storage Provider**: Chọn AWS S3, Wasabi, DigitalOcean hoặc Custom
   - **Custom Endpoint**: (chỉ cho custom provider)
   - **Bucket Name**: Tên bucket của bạn
   - **Access Key ID**: Access key
   - **Secret Access Key**: Secret key
   - **Region**: Khu vực của bucket

3. **Advanced Features:**
   - ✅ **Hybrid Mode**: Lưu files cả local và cloud
   - ✅ **Fallback Mechanism**: Dự phòng khi cloud lỗi
   - ✅ **Auto Sync**: Đồng bộ tự động khi upload
   - ✅ **Cron Jobs**: Tác vụ bảo trì theo lịch
   - ✅ **Cleanup Orphaned Files**: Tự động dọn dẹp

4. **Scheduled Tasks:**
   - **Frequency**: Hourly, Daily, Weekly, Monthly
   - **Auto Cleanup**: Xóa files không sử dụng
   - **Health Checks**: Kiểm tra trạng thái storage

## Sử dụng / Usage

### Đồng bộ Files hiện có / Sync Existing Files

1. Vào plugin settings
2. Nhấp **"Start Sync"** trong Media Library Sync section
3. Plugin sẽ upload tất cả files local lên cloud storage

### Dọn dẹp Storage / Storage Cleanup

1. Vào plugin settings
2. Nhấp **"Start Cleanup"** trong Storage Cleanup section
3. Plugin sẽ xóa các files mồ côi không còn được sử dụng

### Kiểm tra Kết nối / Connection Test

1. Điền thông tin cấu hình
2. Nhấp **"Test Connection"**
3. Xem kết quả test để đảm bảo cấu hình đúng

### Hybrid Mode Usage

Khi bật Hybrid Mode:
- Files được lưu cả local và cloud
- Tăng độ tin cậy và hiệu suất
- Tự động fallback khi một storage lỗi
- Sync định kỳ để đảm bảo consistency

### Scheduled Maintenance

Plugin tự động chạy các tác vụ:
- **Sync**: Đồng bộ files mới lên cloud
- **Cleanup**: Xóa files mồ côi
- **Health Check**: Kiểm tra kết nối và trạng thái
- **Statistics**: Thu thập thông tin sử dụng storage

## API Documentation

### File Manager Methods

```php
// Upload file to cloud
$fileManager->uploadFile($localPath, $cloudPath);

// Download file from cloud
$fileManager->downloadFile($cloudPath, $localPath);

// Check if file exists
$exists = $fileManager->fileExists($cloudPath);

// Get public URL
$url = $fileManager->getPublicUrl($cloudPath);

// Get temporary URL (private files)
$tempUrl = $fileManager->getTemporaryUrl($cloudPath, 3600);

// Sync directory to cloud
$results = $fileManager->syncToCloud($localDir, $cloudDir);

// Cleanup orphaned files
$results = $fileManager->cleanupOrphanedFiles($validFiles);

// Get storage statistics
$stats = $fileManager->getStorageStats();
```

### Cron Job Configuration

Thêm vào OJS crontab:
```bash
# Chạy maintenance hàng giờ
0 * * * * php /path/to/ojs/tools/runScheduledTasks.php plugins/generic/s3Storage/S3StorageCronHandler

# Hoặc chạy hàng ngày
0 2 * * * php /path/to/ojs/tools/runScheduledTasks.php plugins/generic/s3Storage/S3StorageCronHandler
```

## Troubleshooting

### Lỗi thường gặp / Common Issues

**1. Connection Failed:**
```
Solution:
- Kiểm tra credentials (access key, secret key)
- Xác nhận bucket tồn tại và có quyền truy cập
- Kiểm tra endpoint URL (cho custom providers)
- Verify region settings
```

**2. Upload Failed:**
```
Solution:
- Kiểm tra dung lượng bucket
- Xác nhận quyền s3:PutObject
- Kiểm tra file size limits
- Verify SSL/HTTPS settings
```

**3. Sync Issues:**
```
Solution:
- Kiểm tra file permissions trên local storage
- Xác nhận network connectivity
- Check available disk space
- Review error logs
```

**4. Hybrid Mode Problems:**
```
Solution:
- Ensure both local and cloud storage are accessible
- Check sync frequency settings
- Verify fallback mechanism is enabled
- Review maintenance logs
```

### Debug Mode

Bật debug logging:
```php
// Trong config.inc.php
define('DEBUG', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Log Files

Kiểm tra log files:
```bash
# OJS error log
tail -f files/usageStats/processing.log

# System error log
tail -f /var/log/apache2/error.log

# Plugin specific logs
tail -f files/scheduledTasks/s3Storage.log
```

## Performance Optimization

### CDN Integration

Cấu hình CDN để tăng tốc:
```
AWS CloudFront + S3:
- Distribution domain: d1234567890.cloudfront.net
- Origin: your-bucket.s3.amazonaws.com

Wasabi CDN:
- Enable Wasabi CDN in console
- Use provided CDN domain

DigitalOcean CDN:
- Enable CDN for your Space
- Use provided CDN endpoint
```

### Caching Strategies

```php
// Cấu hình cache headers
$fileManager->setCacheHeaders([
    'Cache-Control' => 'public, max-age=31536000',
    'Expires' => gmdate('D, d M Y H:i:s T', time() + 31536000)
]);
```

### Bandwidth Optimization

- Sử dụng compression cho files lớn
- Implement progressive uploads
- Enable multipart uploads cho files > 100MB
- Sử dụng CDN cho static assets

## Security Best Practices

### Bucket Security

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "DenyInsecureConnections",
      "Effect": "Deny",
      "Principal": "*",
      "Action": "s3:*",
      "Resource": "arn:aws:s3:::my-bucket/*",
      "Condition": {
        "Bool": {
          "aws:SecureTransport": "false"
        }
      }
    }
  ]
}
```

### Access Controls

- Sử dụng IAM roles thay vì access keys (khi có thể)
- Regularly rotate access credentials
- Enable MFA cho sensitive operations
- Monitor access logs

### Data Encryption

- Enable server-side encryption (SSE-S3, SSE-KMS)
- Use HTTPS for all transfers
- Encrypt sensitive data before upload
- Implement client-side encryption for highly sensitive content

## Monitoring & Analytics

### Storage Metrics

Plugin cung cấp metrics:
- **File Count**: Số lượng files trên cloud
- **Storage Used**: Dung lượng sử dụng
- **Transfer Stats**: Upload/download bandwidth
- **Error Rates**: Tỷ lệ lỗi operations

### Health Monitoring

```php
// Check storage health
$health = $fileManager->getStorageHealth();

// Monitor key metrics
$metrics = [
    'connectivity' => $health['connection_status'],
    'latency' => $health['average_latency'],
    'error_rate' => $health['error_percentage'],
    'storage_usage' => $health['storage_utilization']
];
```

### Alerting

Cấu hình alerts cho:
- Connection failures
- High error rates
- Storage quota exceeded
- Sync failures

## Backup & Recovery

### Automated Backups

```bash
# Backup plugin settings
mysqldump -u user -p database_name plugin_settings > s3_plugin_backup.sql

# Backup file mappings
mysqldump -u user -p database_name submission_files > file_mappings_backup.sql
```

### Disaster Recovery

1. **Cloud Storage Failure:**
   - Fallback mechanism automatically activates
   - Local copies remain available
   - Restore cloud storage from backups

2. **Local Storage Failure:**
   - Files remain accessible from cloud
   - Download and restore local copies
   - Re-enable hybrid mode

3. **Complete Failure:**
   - Restore from offsite backups
   - Reconfigure plugin settings
   - Re-sync all files

## Migration Guide

### From Local to Cloud

```bash
# 1. Backup current files
tar -czf ojs_files_backup.tar.gz files/

# 2. Configure plugin
# 3. Run initial sync
# 4. Verify all files transferred
# 5. Enable cloud-only mode (optional)
```

### Between Cloud Providers

```bash
# 1. Configure new provider
# 2. Enable hybrid mode
# 3. Sync to new provider
# 4. Update settings
# 5. Cleanup old provider
```

## Contributing

### Development Setup

```bash
git clone https://github.com/your-repo/s3Storage.git
cd s3Storage
composer install
npm install
```

### Running Tests

```bash
# Unit tests
vendor/bin/phpunit tests/unit/

# Integration tests
vendor/bin/phpunit tests/integration/

# E2E tests
vendor/bin/phpunit tests/e2e/
```

### Code Standards

- PSR-4 autoloading
- PSR-12 coding standards
- PHPDoc documentation
- Unit test coverage > 80%

## Support

### Community Support

- **GitHub Issues**: https://github.com/your-repo/s3Storage/issues
- **PKP Community Forum**: https://forum.pkp.sfu.ca/
- **Documentation**: https://docs.pkp.sfu.ca/

### Commercial Support

Contact PKP Publishing Services for:
- Custom development
- Priority support
- Training and consultation
- Enterprise deployments

## License

GNU General Public License v3.0

## Changelog

### v1.0.0 (2023-12-01)
- ✨ Initial release với S3-compatible support
- 🌐 Multi-language support (vi, en)
- 🔄 Hybrid mode implementation
- 📋 Automatic sync and cleanup
- ⚡ Cron job scheduling
- 🛡️ Fallback mechanisms
- 🔧 Advanced configuration options

## Roadmap

### v1.1.0 (Planned)
- 📊 Advanced analytics dashboard
- 🔄 Real-time sync notifications
- 🎯 File compression options
- 🔐 Enhanced security features

### v1.2.0 (Future)
- 🌍 Additional provider support
- 🚀 Performance optimizations
- 📱 Mobile app integration
- 🤖 AI-powered file organization

---

**Lưu ý quan trọng:** Plugin này yêu cầu kiến thức cơ bản về cloud storage và OJS administration. Luôn backup dữ liệu trước khi cài đặt. Để được hỗ trợ, vui lòng tham khảo documentation hoặc liên hệ community support. 