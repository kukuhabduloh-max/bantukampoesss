<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'bantukampus_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function uploadGambarAman($fileInput, $subFolder) {
    if (!isset($fileInput) || $fileInput['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'File gambar wajib diunggah.'];
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        return ['error' => 'Format file harus JPG, PNG, atau WEBP.'];
    }

    if ($fileInput['size'] > 2 * 1024 * 1024) {
        return ['error' => 'Ukuran file maksimal 2 MB.'];
    }

    $targetDir = __DIR__ . "/../uploads/$subFolder/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $newFilename = uniqid($subFolder . '_', true) . '.' . $ext;
    if (move_uploaded_file($fileInput['tmp_name'], $targetDir . $newFilename)) {
        return ['path' => "uploads/$subFolder/$newFilename"];
    }

    return ['error' => 'Gagal menyimpan file ke server.'];
}
?>