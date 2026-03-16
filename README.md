# 🎓 BKUN – Bảng Điểm Cá Nhân (GPA Tracker)
BKUN là một web app giúp sinh viên **quản lý bảng điểm cá nhân, tính GPA và theo dõi tiến độ học tập** một cách trực quan.
Ứng dụng cho phép nhập điểm từng môn học, tự động tính:
- Điểm học phần
- Điểm chữ
- GPA hệ 4
- GPA học kỳ
- GPA tích lũy
---
## 🚀 Demo
Website được thiết kế tối ưu cho:
- 📱 Mobile
- 💻 Desktop
- ⚡ Hosting miễn phí (InfinityFree, 000webhost,...)
---
# ✨ Tính năng
### 📚 Quản lý học kỳ
- Tạo nhiều học kỳ
- Xóa / chỉnh sửa học kỳ
- Theo dõi thống kê từng học kỳ
### 📝 Quản lý môn học
- Thêm môn học
- Sửa môn học
- Xóa môn học
### 🧮 Tính điểm tự động

#### Môn lý thuyết
Điểm HP = (HS1 + 2 × ΣHS2 + 3 × HS3) ÷ (1 + 2m + 3)
Trong đó:
- HS1: chuyên cần
- HS2: kiểm tra
- HS3: thi cuối kỳ
- m: số bài kiểm tra
#### Môn thực hành
Điểm HP = Trung bình các bài thực hành
---
### 📊 Thống kê tự động
- GPA học kỳ
- GPA tích lũy
- Tổng tín chỉ
- Số môn học
- Xếp loại học lực
| GPA | Xếp loại |

|----|----|

| ≥ 3.6 | Xuất sắc |

| ≥ 3.2 | Giỏi |

| ≥ 2.5 | Khá |

| ≥ 2.0 | Trung bình |

| < 2.0 | Yếu |

---
# 🖥️ Công nghệ sử dụng
Frontend
- HTML
- CSS
- Vanilla JavaScript
Backend
- PHP
- MySQL
Không sử dụng framework để giữ project **nhẹ và dễ deploy**.
---
# 📂 Cấu trúc project

BKUN-GPA-Tracker

- index.html # giao diện chính

- api.php # API backend

- config.php # cấu hình database

- database.sql # cấu trúc database

---

# ⚙️ Cài đặt
### 1️⃣ Clone project

---
### 2️⃣ Import database

Import file:

- database.sql

- vào MySQL.

---
### 3️⃣ Cấu hình database
Mở file:

- config.php

Chỉnh sửa các mục sau:
- DB_HOST

- DB_NAME

- DB_USER

- DB_PASS

---
### 4️⃣ Upload lên hosting
Upload toàn bộ source lên hosting:
- InfinityFree
- 000WebHost
- Hostinger
- hoặc Localhost (XAMPP)
---
# 🔐 Hệ thống đăng nhập
Ứng dụng hỗ trợ:
- đăng ký tài khoản
- đăng nhập
- lưu session bằng `localStorage`
- API token authentication
---
# 📱 Tối ưu giao diện
Website được thiết kế:
- responsive mobile
- giao diện hiện đại
- animation background
- UI dạng dashboard
---
# 📌 Lưu ý
Project được tạo ra để:
- hỗ trợ sinh viên tính GPA
- học tập và tham khảo code
Không liên kết chính thức với bất kỳ trường đại học nào.
---
# 🤝 Đóng góp
Mọi đóng góp đều được hoan nghênh.
Bạn có thể:
- fork project
- tạo pull request
- báo lỗi (issue)
---
