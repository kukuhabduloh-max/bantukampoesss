<?php
require_once 'config/database.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT s.*, u.name AS provider_name, u.whatsapp, u.nim, u.is_verified 
                       FROM services s 
                       JOIN users u ON s.provider_id = u.id 
                       WHERE s.id = ?");
$stmt->execute([$id]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: index.php');
    exit;
}

// Ambil ulasan untuk layanan ini
$stmtRev = $pdo->prepare("SELECT r.*, u.name AS client_name 
                          FROM reviews r 
                          JOIN users u ON r.client_id = u.id 
                          WHERE r.service_id = ? 
                          ORDER BY r.created_at DESC");
$stmtRev->execute([$id]);
$reviews = $stmtRev->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }

    $deadline = $_POST['deadline'] ?? '';
    $notes    = trim($_POST['notes'] ?? '');

    if ($deadline === '' || $notes === '') {
        $error = 'Mohon lengkapi tanggal deadline dan catatan instruksi pesanan.';
    } else {
        $stmtOrder = $pdo->prepare("INSERT INTO orders (service_id, client_id, deadline, notes, total_price) VALUES (?, ?, ?, ?, ?)");
        $stmtOrder->execute([
            $service['id'],
            $_SESSION['user']['id'],
            $deadline,
            $notes,
            $service['price']
        ]);
        header('Location: dashboard.php?msg=order_success');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($service['title']) ?> — BantuKampus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <nav class="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="text-xs sm:text-sm font-bold text-slate-600 hover:text-indigo-600 transition">← Kembali ke Katalog</a>
            <a href="index.php" class="font-extrabold text-lg text-slate-900">Bantu<span class="text-indigo-600">Kampus</span></a>
            <div>
                <?php if (isset($_SESSION['user'])): ?>
                    <a href="dashboard.php" class="text-xs font-bold text-indigo-600 hover:underline">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="text-xs font-bold text-indigo-600 hover:underline">Masuk</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-10 grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <div class="lg:col-span-7 space-y-6">
            <section class="bg-white rounded-3xl p-7 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700">
                        <?= htmlspecialchars($service['category']) ?>
                    </span>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                        ⏱ Estimasi <?= (int)$service['estimate_days'] ?> Hari Kerja
                    </span>
                </div>

                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 leading-tight">
                    <?= htmlspecialchars($service['title']) ?>
                </h1>

                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/70 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-slate-900"><?= htmlspecialchars($service['provider_name']) ?></span>
                            <?php if ($service['is_verified']): ?>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">✓ Verified KTM</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">NIM Terdaftar: <?= htmlspecialchars($service['nim'] ?? '-') ?></p>
                    </div>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $service['whatsapp']) ?>?text=Halo%20kak,%20saya%20mau%20tanya%20jasa%20<?= urlencode($service['title']) ?>%20di%20BantuKampus" 
                       target="_blank" 
                       class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition">
                        Chat WhatsApp
                    </a>
                </div>

                <div>
                    <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-2">Deskripsi &amp; Cakupan Layanan</h2>
                    <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line">
                        <?= htmlspecialchars($service['description']) ?>
                    </p>
                </div>
            </section>

            <!-- Bagian Ulasan Pembeli -->
            <section class="bg-white rounded-3xl p-7 sm:p-8 border border-slate-200/80 shadow-sm space-y-4">
                <h2 class="text-base font-extrabold text-slate-900">Ulasan Pemesan (<?= count($reviews) ?>)</h2>
                <?php if (empty($reviews)): ?>
                    <p class="text-xs text-slate-500">Belum ada ulasan untuk layanan ini.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/60 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-900"><?= htmlspecialchars($rev['client_name']) ?></span>
                                    <span class="text-xs font-bold text-amber-500"><?= str_repeat('★', (int)$rev['rating']) ?></span>
                                </div>
                                <p class="text-xs text-slate-600"><?= htmlspecialchars($rev['comment']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <section class="lg:col-span-5">
            <div class="bg-white rounded-3xl p-7 border border-slate-200/80 shadow-sm space-y-5 sticky top-24">
                <div class="border-b border-slate-100 pb-4">
                    <span class="text-xs text-slate-400 font-semibold">Biaya Mulai Dari</span>
                    <div class="text-3xl font-extrabold text-slate-900 mt-0.5"><?= formatRupiah($service['price']) ?></div>
                </div>

                <?php if ($error): ?>
                    <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (!isset($_SESSION['user'])): ?>
                    <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs space-y-3">
                        <p class="font-semibold">Silakan masuk terlebih dahulu untuk memesan layanan jasa ini.</p>
                        <a href="login.php" class="block w-full text-center py-2.5 rounded-xl bg-indigo-600 text-white font-bold">Masuk ke Akun</a>
                    </div>
                <?php elseif ($_SESSION['user']['id'] == $service['provider_id']): ?>
                    <div class="p-4 rounded-2xl bg-slate-100 text-slate-600 text-xs font-medium">
                        Ini adalah layanan jasa milikmu sendiri. Kelola layanan ini melalui <a href="dashboard.php" class="text-indigo-600 font-bold underline">Dashboard</a>.
                    </div>
                <?php else: ?>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Target Deadline Pengerjaan *</label>
                            <input type="date" name="deadline" required min="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Instruksi / Rincian Tugas &amp; Link Bahan *</label>
                            <textarea name="notes" rows="4" required placeholder="Contoh: Ketik ulang PDF 12 halaman pakai Times New Roman 12. Link bahan: https://..." class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm"></textarea>
                        </div>

                        <div class="p-3 rounded-xl bg-indigo-50/70 border border-indigo-100 text-[11px] text-indigo-900">
                            🛡️ <strong>Alur Aman:</strong> Setelah memesan, unggah bukti pembayaran di Dashboard agar penyedia jasa segera memproses tugasmu.
                        </div>

                        <button type="submit" class="w-full py-3.5 rounded-2xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 transition shadow-sm">
                            Buat Pesanan Sekarang
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>