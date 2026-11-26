<?php
require_once 'config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $nim      = trim($_POST['nim'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $role     = ($_POST['role'] ?? 'client') === 'provider' ? 'provider' : 'client';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

    $ktmPath = null;
    $isVerified = 0;

    if ($role === 'provider') {
        $upload = uploadGambarAman($_FILES['ktm_file'] ?? null, 'ktm');
        if (isset($upload['error'])) {
            $error = 'Verifikasi Penyedia Jasa gagal: ' . $upload['error'];
        } else {
            $ktmPath = $upload['path'];
            // Otomatis terverifikasi setelah mengunggah KTM & NIM (bisa dicek di dashboard)
            $isVerified = 1;
        }
    }

    if ($error === '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, whatsapp, nim, ktm_file, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $role, $whatsapp, $nim, $ktmPath, $isVerified]);
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Email sudah terdaftar, silakan gunakan email lain.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar &amp; Verifikasi Akun — BantuKampus</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white max-w-md w-full rounded-3xl p-8 shadow-sm border border-slate-200 space-y-5">
        <div class="text-center">
            <a href="index.php" class="inline-block font-extrabold text-2xl text-slate-900">Bantu<span class="text-indigo-600">Kampus</span></a>
            <p class="text-xs text-slate-500 mt-1">Pendaftaran dengan verifikasi identitas mahasiswa</p>
        </div>

        <?php if ($error): ?>
            <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-3.5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap *</label>
                <input type="text" name="name" required placeholder="Nama Mahasiswa" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">NIM *</label>
                    <input type="text" name="nim" required placeholder="2211102xxx" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">WhatsApp (628...) *</label>
                    <input type="text" name="whatsapp" required placeholder="628123456789" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Email *</label>
                <input type="email" name="email" required placeholder="nama@student.ac.id" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Daftar Sebagai *</label>
                <select name="role" id="roleSelect" onchange="toggleKtmField()" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm bg-white">
                    <option value="client">Mahasiswa Pemesan Jasa (Klien)</option>
                    <option value="provider">Penyedia Jasa (Wajib Upload KTM)</option>
                </select>
            </div>
            <div id="ktmBox" class="hidden p-3.5 rounded-2xl bg-indigo-50 border border-indigo-200 space-y-1.5">
                <label class="block text-xs font-bold text-indigo-900">Unggah Foto KTM (JPG/PNG, Maks 2MB) *</label>
                <input type="file" name="ktm_file" id="ktmInput" accept=".jpg,.jpeg,.png,.webp" class="w-full text-xs text-slate-600">
                <p class="text-[11px] text-indigo-700">Untuk mencegah penipuan, penyedia jasa wajib memverifikasi Kartu Tanda Mahasiswa.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Password *</label>
                <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm">
            </div>
            <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 transition">Daftar &amp; Verifikasi</button>
        </form>

        <p class="text-center text-xs text-slate-500">
            Sudah punya akun? <a href="login.php" class="text-indigo-600 font-bold hover:underline">Masuk di sini</a>
        </p>
    </div>

    <script>
        function toggleKtmField() {
            const role = document.getElementById('roleSelect').value;
            const ktmBox = document.getElementById('ktmBox');
            const ktmInput = document.getElementById('ktmInput');
            if (role === 'provider') {
                ktmBox.classList.remove('hidden');
                ktmInput.required = true;
            } else {
                ktmBox.classList.add('hidden');
                ktmInput.required = false;
            }
        }
    </script>
</body>
</html>