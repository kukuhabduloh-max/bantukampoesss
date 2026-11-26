# BantuKampus — Marketplace Jasa Mahasiswa Terverifikasi

Aplikasi web marketplace layanan jasa mahasiswa (ketik laporan praktikum, desain PPT, perbaikan koding, dan jastip kampus) berbasis **PHP Native**, **MySQL (PDO)**, dan **Tailwind CSS**.

## Fitur Utama
- **Autentikasi & Verifikasi KTM:** Registrasi multi-role (Klien & Penyedia Jasa) dengan unggah foto KTM untuk verifikasi identitas mahasiswa.
- **Katalog & Filter:** Pencarian layanan dan filter berdasarkan kategori jasa beserta skor ulasan.
- **CRUD Layanan Jasa:** Penyedia jasa dapat menambah, mengubah, dan menghapus paket layanan.
- **Alur Transaksi & Bukti Pembayaran:** Klien mengunggah bukti transfer dan penyedia jasa memverifikasi status pembayaran (`Belum Bayar` -> `Menunggu Verifikasi` -> `Lunas`).
- **Sistem Rating & Ulasan:** Pembeli dapat memberikan bintang (1–5) dan ulasan setelah pesanan berstatus `Selesai`.

## Cara Menjalankan di Lokal (XAMPP)
1. Salin folder proyek ke direktori `C:\xampp\htdocs\bantukampus\`.
2. Jalankan **Apache** dan **MySQL** melalui XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`, buat database baru bernama `bantukampus_db`, lalu import file `database.sql`.
4. Akses aplikasi melalui browser di `http://localhost/bantukampus/`.