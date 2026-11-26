<?php
require_once 'config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Email atau password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — BantuKampus</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white max-w-md w-full rounded-3xl p-8 shadow-sm border border-slate-200 space-y-6">
        <div class="text-center">
            <a href="index.php" class="inline-block font-extrabold text-2xl text-slate-900">Bantu<span class="text-indigo-600">Kampus</span></a>
            <p class="text-xs text-slate-500 mt-1">Masuk untuk memesan atau mengelola layanan jasa</p>
        </div>

        <?php if ($error): ?>
            <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Email Kampus / Pribadi</label>
                <input type="email" name="email" required placeholder="penyedia@kampus.ac.id" class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required placeholder="••••••" class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm">
            </div>
            <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 transition">Masuk Sekarang</button>
        </form>

        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-[11px] text-slate-600 space-y-1">
            <p class="font-bold text-slate-800">Akun Demo Cepat (Pass: 123456):</p>
            <p>• Penyedia Jasa: <code>penyedia@kampus.ac.id</code></p>
            <p>• Pemesan / Klien: <code>klien@kampus.ac.id</code></p>
        </div>

        <p class="text-center text-xs text-slate-500">
            Belum punya akun? <a href="register.php" class="text-indigo-600 font-bold hover:underline">Daftar di sini</a>
        </p>
    </div>
</body>
</html>