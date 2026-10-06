# Kho dữ liệu

Trang web để **chủ máy** (tối đa 2 người) tải dữ liệu lên, còn nhân viên chỉ **xem, tải về và gửi góp ý**.

## Cách hoạt động (đơn giản)
- Toàn bộ code nằm trong 1 tệp: `index.php`.
- Dữ liệu (tài khoản, nhật ký, danh sách tệp) lưu trong tệp `storage/data.db`. Tệp bạn tải lên cũng nằm trong `storage/`.
- Thư mục `storage/` bị khoá, không ai mở trực tiếp bằng đường link được. Mọi lượt tải đều đi qua trang web nên được ghi nhật ký.
- Tệp tự phân loại theo đuôi (Hình ảnh, Tài liệu, Bảng tính, Video...).

## 1. Chạy thử trên máy Windows
1. Cài PHP: vào https://windows.php.net/download, tải bản "Thread Safe" zip, giải nén vào `C:\php`.
2. Mở thư mục `kho-du-lieu`, gõ `cmd` lên thanh địa chỉ, Enter.
3. Chạy: `C:\php\php.exe -S localhost:8000`
4. Mở trình duyệt vào http://localhost:8000. Lần đầu sẽ yêu cầu tạo tài khoản chủ máy.

## 2. Đưa lên GitHub
1. Cài **GitHub Desktop** (https://desktop.github.com) và đăng nhập.
2. Chọn *File > Add local repository*, chọn thư mục `kho-du-lieu`.
3. Bấm *Commit to main*, rồi *Publish repository*.
4. **Chọn Private** (riêng tư). Tệp `.gitignore` đã giúp KHÔNG đẩy thư mục `storage/` (dữ liệu thật) lên GitHub.

> GitHub chỉ để lưu code. GitHub Pages **không chạy được PHP**, nên không dùng nó để chạy web này.

## 3. Đưa lên Hostinger
Cần gói hosting có PHP (Premium/Business Web Hosting đều có).
1. Vào hPanel > *Websites* > chọn site > *File Manager*.
2. Mở thư mục `public_html`, tải `index.php` lên.
3. Vào *Advanced > PHP Configuration*: chọn PHP 8.1 trở lên, bật extension `pdo_sqlite`; tăng `upload_max_filesize` và `post_max_size` nếu cần tải tệp lớn.
4. Bật SSL (HTTPS) miễn phí trong hPanel.
5. Mở tên miền của bạn, tạo tài khoản chủ máy ngay (người đầu tiên vào sẽ là chủ máy!).

Muốn tự động cập nhật từ GitHub: hPanel > *Advanced > Git*, dán địa chỉ repo, chọn thư mục `public_html`.

## Lưu ý bảo mật
- Dùng mật khẩu dài, khác nhau cho từng người.
- Sao lưu thư mục `storage/` định kỳ (tải về máy).
- Nếu nhập sai mật khẩu, hệ thống chờ 1 giây và ghi nhật ký.
