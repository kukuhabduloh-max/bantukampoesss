CREATE DATABASE IF NOT EXISTS bantukampus_db;
USE bantukampus_db;

-- 1. Tabel Users (Penyedia Jasa & Pemesan)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('client', 'provider') NOT NULL DEFAULT 'client',
    whatsapp VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabel Services (Daftar Layanan Jasa)
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    category ENUM('Akademik & Laprak', 'Desain & PPT', 'IT & Koding', 'Jastip & Harian') NOT NULL,
    price INT NOT NULL,
    estimate_days INT NOT NULL DEFAULT 1,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Tabel Orders (Riwayat Pemesanan Jasa)
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    client_id INT NOT NULL,
    deadline DATE NOT NULL,
    notes TEXT NOT NULL,
    total_price INT NOT NULL,
    status ENUM('Menunggu Konfirmasi', 'Dikerjakan', 'Selesai', 'Dibatalkan') NOT NULL DEFAULT 'Menunggu Konfirmasi',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Akun Demo (Password semua akun: 123456)
INSERT INTO users (name, email, password, role, whatsapp) VALUES
('Kukuh Abduloh (Penyedia)', 'penyedia@kampus.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'provider', '6281234567890'),
('Siti Mahasiswa (Klien)', 'klien@kampus.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client', '6289876543210');

-- Data Dummy Jasa Awal
INSERT INTO services (provider_id, title, category, price, estimate_days, description) VALUES
(1, 'Jasa Ketik & Rapikan Format Laprak / Makalah Word', 'Akademik & Laprak', 15000, 1, 'Terima ketik ulang tulisan tangan/PDF ke Microsoft Word, perapihan margin, daftar isi otomatis, daftar pustaka Mendeley/APA, dan penomoran halaman.'),
(1, 'Desain Slide PPT Presentasi Sidang / Tugas Kelompok', 'Desain & PPT', 25000, 2, 'Pembuatan slide presentasi modern, bersih, dan visual menarik (maksimal 15 slide). Bebas revisi minor 2x.'),
(1, 'Bantu Debugging Error PHP / MySQL / Tugas Web Dasar', 'IT & Koding', 35000, 2, 'Bantu cari dan perbaiki error pada proyek PHP Native, koneksi database MySQL, atau perapihan layout HTML/CSS/Tailwind.'),
(1, 'Jastip Print, Jilid Lakban/Spiral & Antar ke Kampus', 'Jastip & Harian', 10000, 1, 'Kirim file PDF kamu, kami print dan jilid rapi lalu antar langsung ke gerbang kampus atau kos area sekitar kampus.');