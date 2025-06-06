# S3-Compatible Storage Plugin for OJS 3.4.0

## Mô tả / Description

**Tiếng Việt:**
Plugin này tích hợp lưu trữ tương thích S3 cho Open Journal Systems (OJS) 3.4.0, hỗ trợ nhiều nhà cung cấp như AWS S3, Wasabi, DigitalOcean Spaces và các dịch vụ tương thích S3 khác. Plugin cung cấp giải pháp lưu trữ đám mây với các tính năng tiên tiến như chế độ lai (hybrid mode), cơ chế dự phòng (fallback), đồng bộ tự động và quản lý tệp tin thông minh.

**English:**
This plugin integrates S3-compatible storage for Open Journal Systems (OJS) 3.4.0, supporting multiple providers including AWS S3, Wasabi, DigitalOcean Spaces, and other S3-compatible services. The plugin provides advanced cloud storage solutions with features like hybrid mode, fallback mechanisms, automatic synchronization, and intelligent file management.

## Tính năng / Features

### 🔄 Core Features
- **S3-Compatible Integration**: Hỗ trợ AWS S3, Wasabi, DigitalOcean Spaces và custom endpoints.
- **Hybrid Mode**: Lưu trữ files cả ở local và cloud để dự phòng.
- **Fallback Mechanism**: Tự động chuyển sang local storage khi cloud không khả dụng.
- **Media Library Sync**: Đồng bộ tự động files OJS tới cloud storage.

### ⚡ Advanced Features
- **Custom Cron Jobs**: Lên lịch tác vụ bảo trì và dọn dẹp tự động.
- **Efficient File Management**: Tự động xóa files mồ côi và không sử dụng một cách hiệu quả, ngay cả với các bucket lớn.
- **Customizable Settings**: Giao diện cấu hình đầy đủ và dễ sử dụng với các tùy chọn động.
- **Real-time Sync**: Đồng bộ files ngay khi upload (tùy chọn).

### 🌐 Multi-language Support
- Hỗ trợ đa ngôn ngữ (Tiếng Việt và Tiếng Anh).
- Giao diện admin hoàn toàn được dịch.

### 🔒 Security & Reliability
- Mã hóa SSL/HTTPS cho truyền tải an toàn.
- Kiểm tra kết nối và health checks.
- Quản lý quyền truy cập chi tiết.

## Cài đặt / Installation

### Qua file ZIP (khuyến nghị)

1. Tải phiên bản mới nhất từ trang [Releases](https://github.com/your-repo/s3Storage/releases).
2. Đăng nhập vào OJS với quyền quản trị.
3. Vào **Settings > Website > Plugins > Upload A New Plugin**.
4. Tải file ZIP của plugin lên và làm theo hướng dẫn.

### Qua Git (cho nhà phát triển)
```bash
cd plugins/generic/
git clone https://github.com/your-repo/s3Storage.git
```

### Cài đặt dependencies
Sau khi có mã nguồn, chạy lệnh sau từ thư mục của plugin:
```bash
cd plugins/generic/s3Storage/
composer install --no-dev
```

### Kích hoạt plugin
1. Đăng nhập vào OJS với quyền quản trị.
2. Vào **Settings > Website > Plugins**.
3. Tìm "S3 Storage Plugin" trong danh sách Generic Plugins.
4. Nhấp **Enable** để kích hoạt.

### Cài đặt qua dòng lệnh (CLI)
Bạn có thể cài đặt và kích hoạt plugin qua CLI. Chạy các lệnh sau từ thư mục gốc của OJS:
```bash
# Cài đặt hoặc cập nhật plugin
php lib/pkp/tools/installPluginVersion.php plugins/generic/s3Storage/version.xml

# Kích hoạt plugin cho một journal (thay a_journal_path bằng đường dẫn của journal)
php tools/plugin.php enable S3StoragePlugin a_journal_path
```

## Cấu hình Cron Job

Để các tác vụ tự động (dọn dẹp, đồng bộ) hoạt động, bạn cần cấu hình một cron job trên server của mình để thực thi script của OJS.

**Lệnh Cron Job:**
```bash
* * * * * php /path/to/your/ojs/tools/runScheduledTasks.php
```
*Lệnh này nên được chạy thường xuyên (ví dụ: mỗi giờ). Plugin sẽ tự quyết định có thực thi các tác vụ hay không dựa trên cài đặt của bạn trong giao diện admin.*

Trong trang cài đặt plugin, bạn có thể bật/tắt các tác vụ cron và chọn những hành động nào sẽ được thực hiện (dọn dẹp, đồng bộ).

## Troubleshooting

### Lỗi `The tar command is not available`
Lỗi này xảy ra khi cài đặt plugin qua giao diện web nếu OJS không thể tìm thấy lệnh `tar` trên server của bạn.
**Giải pháp:**
1. Mở file `config.inc.php` trong thư mục gốc của OJS.
2. Tìm đến phần `[cli]`.
3. Cung cấp đường dẫn chính xác đến lệnh `tar`. Ví dụ:
   ```ini
   tar = /bin/tar
   ```
Tham khảo thêm tại: [OJS Services](https://ojs-services.com/ojs-plugins/how-to-resolve-plugin-installation-error-in-ojs/)

### Vấn đề tương thích khi nâng cấp OJS
Khi nâng cấp phiên bản OJS, một số plugin có thể không tương thích. Luôn kiểm tra Plugin Gallery và tài liệu của plugin trước khi nâng cấp.
**Giải pháp:**
- Luôn sao lưu hệ thống trước khi nâng cấp.
- Nâng cấp plugin qua Plugin Gallery trong OJS để đảm bảo tính tương thích.
- Nếu cài đặt thủ công, hãy chắc chắn bạn đang dùng phiên bản plugin hỗ trợ phiên bản OJS của bạn.
Tham khảo thêm tại: [PKP Community Forum](http://forum.pkp.sfu.ca/t/commandline-upgrade-of-plugins/77385)

## Contributing

Chúng tôi hoan nghênh mọi đóng góp! Vui lòng xem `CONTRIBUTING.md` để biết thêm chi tiết về cách đóng góp, báo lỗi và các tiêu chuẩn code.

## License

GNU General Public License v3.0

---

**Lưu ý quan trọng:** Plugin này yêu cầu kiến thức cơ bản về cloud storage và quản trị OJS. Luôn sao lưu dữ liệu trước khi cài đặt hoặc thay đổi cấu hình. Để được hỗ trợ, vui lòng tạo một issue trên GitHub. 