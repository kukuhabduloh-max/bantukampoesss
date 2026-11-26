<?php
require_once 'config/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Refresh data user dari DB agar status verifikasi selalu terbaru
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user']['id']]);
$user = $stmtUser->fetch();
$_SESSION['user'] = $user;

$userId = (int)$user['id'];
$role   = $user['role'];
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Tambah Jasa Baru (Hanya Provider Terverifikasi)
    if ($action === 'create_service' && $role === 'provider' && $user['is_verified']) {
        $title         = trim($_POST['title'] ?? '');
        $category      = $_POST['category'] ?? 'Akademik & Laprak';
        $price         = (int)($_POST['price'] ?? 0);
        $estimate_days = max(1, (int)($_POST['estimate_days'] ?? 1));
        $description   = trim($_POST['description'] ?? '');

        if ($title !== '' && $price > 0 && $description !== '') {
            $stmt = $pdo->prepare("INSERT INTO services (provider_id, title, category, price, estimate_days, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $title, $category, $price, $estimate_days, $description]);
            header('Location: dashboard.php?msg=service_created');
            exit;
        }
    }

    // 2. Edit Jasa (Provider)
    if ($action === 'update_service' && $role === 'provider') {
        $serviceId     = (int)($_POST['service_id'] ?? 0);
        $title         = trim($_POST['title'] ?? '');
        $category      = $_POST['category'] ?? 'Akademik & Laprak';
        $price         = (int)($_POST['price'] ?? 0);
        $estimate_days = max(1, (int)($_POST['estimate_days'] ?? 1));
        $description   = trim($_POST['description'] ?? '');

        $stmt = $pdo->prepare("UPDATE services SET title = ?, category = ?, price = ?, estimate_days = ?, description = ? WHERE id = ? AND provider_id = ?");
        $stmt->execute([$title, $category, $price, $estimate_days, $description, $serviceId, $userId]);
        header('Location: dashboard.php?msg=service_updated');
        exit;
    }

    // 3. Hapus Jasa (Provider)
    if ($action === 'delete_service' && $role === 'provider') {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ? AND provider_id = ?");
        $stmt->execute([$serviceId, $userId]);
        header('Location: dashboard.php?msg=service_deleted');
        exit;
    }

    // 4. Ubah Status Pesanan & Pembayaran (Provider)
    if ($action === 'update_order_status' && $role === 'provider') {
        $orderId       = (int)($_POST['order_id'] ?? 0);
        $status        = $_POST['status'] ?? 'Menunggu Konfirmasi';
        $paymentStatus = $_POST['payment_status'] ?? 'Belum Bayar';

        $stmt = $pdo->prepare("UPDATE orders o 
                               JOIN services s ON o.service_id = s.id 
                               SET o.status = ?, o.payment_status = ? 
                               WHERE o.id = ? AND s.provider_id = ?");
        $stmt->execute([$status, $paymentStatus, $orderId, $userId]);
        header('Location: dashboard.php?msg=status_updated');
        exit;
    }

    // 5. Upload Bukti Pembayaran (Client)
    if ($action === 'upload_payment' && $role === 'client') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $upload  = uploadGambarAman($_FILES['payment_proof'] ?? null, 'payments');

        if (isset($upload['error'])) {
            $errorMsg = 'Upload bukti transfer gagal: ' . $upload['error'];
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET payment_proof = ?, payment_status = 'Menunggu Verifikasi' WHERE id = ? AND client_id = ?");
            $stmt->execute([$upload['path'], $orderId, $userId]);
            header('Location: dashboard.php?msg=payment_uploaded');
            exit;
        }
    }

    // 6. Kirim Rating & Ulasan (Client setelah status Selesai)
    if ($action === 'submit_review' && $role === 'client') {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $rating    = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment   = trim($_POST['comment'] ?? '');

        if ($comment !== '') {
            $stmt = $pdo->prepare("INSERT IGNORE INTO reviews (order_id, service_id, client_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$orderId, $serviceId, $userId, $rating, $comment]);
            header('Location: dashboard.php?msg=review_submitted');
            exit;
        }
    }

    // 7. Batalkan Pesanan (Client)
    if ($action === 'cancel_order' && $role === 'client') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Dibatalkan' WHERE id = ? AND client_id = ? AND status = 'Menunggu Konfirmasi'");
        $stmt->execute([$orderId, $userId]);
        header('Location: dashboard.php?msg=order_cancelled');
        exit;
    }
}

$categories = ['Akademik & Laprak', 'Desain & PPT', 'IT & Koding', 'Jastip & Harian'];
$editService = null;

if ($role === 'provider') {
    if (isset($_GET['edit'])) {
        $stmtEdit = $pdo->prepare("SELECT * FROM services WHERE id = ? AND provider_id = ?");
        $stmtEdit->execute([(int)$_GET['edit'], $userId]);
        $editService = $stmtEdit->fetch();
    }

    $stmtServices = $pdo->prepare("SELECT * FROM services WHERE provider_id = ? ORDER BY created_at DESC");
    $stmtServices->execute([$userId]);
    $myServices = $stmtServices->fetchAll();

    $stmtOrders = $pdo->prepare("SELECT o.*, s.title AS service_title, u.name AS client_name, u.whatsapp AS client_wa, u.nim AS client_nim 
                                 FROM orders o 
                                 JOIN services s ON o.service_id = s.id 
                                 JOIN users u ON o.client_id = u.id 
                                 WHERE s.provider_id = ? 
                                 ORDER BY o.created_at DESC");
    $stmtOrders->execute([$userId]);
    $incomingOrders = $stmtOrders->fetchAll();
} else {
    $stmtMyOrders = $pdo->prepare("SELECT o.*, s.title AS service_title, s.category, u.name AS provider_name, u.whatsapp AS provider_wa,
                                          r.rating AS user_rating, r.comment AS user_comment
                                   FROM orders o 
                                   JOIN services s ON o.service_id = s.id 
                                   JOIN users u ON s.provider_id = u.id 
                                   LEFT JOIN reviews r ON o.id = r.order_id
                                   WHERE o.client_id = ? 
                                   ORDER BY o.created_at DESC");
    $stmtMyOrders->execute([$userId]);
    $myOrders = $stmtMyOrders->fetchAll();
}

function badgeStatus($status) {
    return match ($status) {
        'Menunggu Konfirmasi', 'Belum Bayar' => 'bg-amber-100 text-amber-800',
        'Dikerjakan', 'Menunggu Verifikasi'  => 'bg-sky-100 text-sky-800',
        'Selesai', 'Lunas'                   => 'bg-emerald-100 text-emerald-800',
        'Dibatalkan'                         => 'bg-rose-100 text-rose-800',
        default                              => 'bg-slate-100 text-slate-800',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — BantuKampus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <nav class="bg-white border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2 font-extrabold text-lg text-slate-900">
                <span class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs">BK</span>
                Bantu<span class="text-indigo-600">Kampus</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-xs sm:text-sm font-semibold text-slate-600 hover:text-indigo-600">Katalog Jasa</a>
                <a href="logout.php" class="px-3.5 py-2 rounded-xl bg-rose-50 text-rose-600 text-xs font-bold hover:bg-rose-100 transition">Keluar</a>
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-8">

        <!-- Header Profil & Status Verifikasi -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold <?= $role === 'provider' ? 'bg-indigo-100 text-indigo-700' : 'bg-lime-200 text-slate-900' ?>">
                        <?= $role === 'provider' ? 'Akun Penyedia Jasa' : 'Akun Mahasiswa Pemesan' ?>
                    </span>
                    <?php if ($role === 'provider' && $user['is_verified']): ?>
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">✓ KTM Terverifikasi (NIM: <?= htmlspecialchars($user['nim'] ?? '-') ?>)</span>
                    <?php endif; ?>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-900 mt-2">Halo, <?= htmlspecialchars($user['name']) ?>!</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Kelola layanan, verifikasi pembayaran, dan ulasan transaksi Anda.</p>
            </div>
            <div class="flex items-center gap-2">
                <?php if (!empty($user['ktm_file'])): ?>
                    <a href="<?= htmlspecialchars($user['ktm_file']) ?>" target="_blank" class="px-4 py-2.5 rounded-2xl bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition">Lihat KTM Saya</a>
                <?php endif; ?>
                <a href="index.php" class="px-5 py-2.5 rounded-2xl bg-slate-900 text-white text-xs font-bold hover:bg-indigo-600 transition">
                    + Katalog Jasa
                </a>
            </div>
        </div>

        <?php if ($errorMsg): ?>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs sm:text-sm font-semibold"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['msg'])): ?>
            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm font-semibold">
                <?php
                $messages = [
                    'service_created'  => 'Layanan jasa baru berhasil dipublikasikan!',
                    'service_updated'  => 'Perubahan data layanan jasa berhasil disimpan.',
                    'service_deleted'  => 'Layanan jasa telah dihapus.',
                    'status_updated'   => 'Status pesanan dan status pembayaran berhasil diperbarui.',
                    'order_success'    => 'Pesanan dibuat! Silakan unggah bukti pembayaran di kartu pesanan di bawah.',
                    'payment_uploaded' => 'Bukti pembayaran berhasil diunggah! Menunggu konfirmasi penyedia jasa.',
                    'review_submitted' => 'Terima kasih! Rating dan ulasanmu telah ditampilkan di halaman jasa.',
                    'order_cancelled'  => 'Pesanan berhasil dibatalkan.'
                ];
                echo $messages[$_GET['msg']] ?? 'Aksi berhasil dijalankan.';
                ?>
            </div>
        <?php endif; ?>

        <?php if ($role === 'provider'): ?>
            <!-- ================= DASHBOARD PENYEDIA JASA ================= -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <section class="lg:col-span-5">
                    <div class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200/80 shadow-sm space-y-4">
                        <h2 class="text-lg font-extrabold text-slate-900">
                            <?= $editService ? 'Edit Layanan Jasa' : '+ Tambah Layanan Jasa Baru' ?>
                        </h2>

                        <form method="POST" class="space-y-3.5">
                            <input type="hidden" name="action" value="<?= $editService ? 'update_service' : 'create_service' ?>">
                            <?php if ($editService): ?>
                                <input type="hidden" name="service_id" value="<?= (int)$editService['id'] ?>">
                            <?php endif; ?>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Layanan Jasa *</label>
                                <input type="text" name="title" required value="<?= htmlspecialchars($editService['title'] ?? '') ?>" placeholder="Misal: Jasa Ketik Laprak & Rapikan Word" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Kategori *</label>
                                    <select name="category" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 text-sm bg-white">
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= $cat ?>" <?= ($editService['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Estimasi (Hari) *</label>
                                    <input type="number" name="estimate_days" min="1" required value="<?= (int)($editService['estimate_days'] ?? 1) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Harga Mulai (Rp) *</label>
                                <input type="number" name="price" min="1000" step="500" required value="<?= htmlspecialchars($editService['price'] ?? '') ?>" placeholder="15000" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Deskripsi &amp; Ketentuan *</label>
                                <textarea name="description" rows="4" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm"><?= htmlspecialchars($editService['description'] ?? '') ?></textarea>
                            </div>

                            <div class="flex gap-2 pt-1">
                                <button type="submit" class="flex-1 py-3 rounded-xl bg-indigo-600 text-white font-bold text-xs sm:text-sm hover:bg-indigo-700 transition">
                                    <?= $editService ? 'Simpan Perubahan' : 'Publikasikan Jasa' ?>
                                </button>
                                <?php if ($editService): ?>
                                    <a href="dashboard.php" class="px-4 py-3 rounded-xl bg-slate-200 text-slate-700 font-bold text-xs sm:text-sm">Batal</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="lg:col-span-7 space-y-4">
                    <h2 class="text-lg font-extrabold text-slate-900">Daftar Layanan Jasa Saya (<?= count($myServices) ?>)</h2>
                    <div class="space-y-3">
                        <?php foreach ($myServices as $srv): ?>
                            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <span class="text-[11px] font-bold text-indigo-600"><?= htmlspecialchars($srv['category']) ?> · <?= (int)$srv['estimate_days'] ?> Hari</span>
                                    <h3 class="text-base font-bold text-slate-900 mt-0.5"><?= htmlspecialchars($srv['title']) ?></h3>
                                    <p class="text-sm font-extrabold text-slate-700 mt-1"><?= formatRupiah($srv['price']) ?></p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <a href="dashboard.php?edit=<?= (int)$srv['id'] ?>" class="px-3.5 py-2 rounded-xl bg-amber-100 text-amber-800 text-xs font-bold hover:bg-amber-200 transition">Edit</a>
                                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus layanan jasa ini?')">
                                        <input type="hidden" name="action" value="delete_service">
                                        <input type="hidden" name="service_id" value="<?= (int)$srv['id'] ?>">
                                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-rose-100 text-rose-700 text-xs font-bold hover:bg-rose-200 transition">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <!-- Pesanan Masuk & Verifikasi Bukti Transfer -->
            <section class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-5">
                <h2 class="text-lg font-extrabold text-slate-900">Pesanan Masuk &amp; Verifikasi Pembayaran (<?= count($incomingOrders) ?>)</h2>

                <?php if (empty($incomingOrders)): ?>
                    <p class="text-xs text-slate-500">Belum ada pesanan masuk saat ini.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs sm:text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-slate-400 text-xs uppercase">
                                    <th class="py-3 pr-4">Pemesan</th>
                                    <th class="py-3 pr-4">Layanan &amp; Instruksi</th>
                                    <th class="py-3 pr-4">Pembayaran</th>
                                    <th class="py-3 pr-4">Progres</th>
                                    <th class="py-3">Kontrol Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($incomingOrders as $ord): ?>
                                    <tr>
                                        <td class="py-4 pr-4 align-top">
                                            <p class="font-bold text-slate-900"><?= htmlspecialchars($ord['client_name']) ?></p>
                                            <p class="text-[11px] text-slate-500">NIM: <?= htmlspecialchars($ord['client_nim'] ?? '-') ?></p>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $ord['client_wa']) ?>" target="_blank" class="text-xs text-emerald-600 font-semibold hover:underline">WA Klien ↗</a>
                                        </td>
                                        <td class="py-4 pr-4 align-top max-w-xs">
                                            <p class="font-bold text-slate-800"><?= htmlspecialchars($ord['service_title']) ?></p>
                                            <p class="text-xs text-slate-500 mt-1"><?= nl2br(htmlspecialchars($ord['notes'])) ?></p>
                                            <p class="text-[11px] font-semibold text-indigo-600 mt-1">Deadline: <?= date('d M Y', strtotime($ord['deadline'])) ?></p>
                                        </td>
                                        <td class="py-4 pr-4 align-top space-y-1.5">
                                            <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold <?= badgeStatus($ord['payment_status']) ?>">
                                                <?= htmlspecialchars($ord['payment_status']) ?>
                                            </span>
                                            <?php if (!empty($ord['payment_proof'])): ?>
                                                <a href="<?= htmlspecialchars($ord['payment_proof']) ?>" target="_blank" class="block text-xs font-bold text-indigo-600 underline">Lihat Bukti Transfer ↗</a>
                                            <?php else: ?>
                                                <span class="block text-[11px] text-slate-400">Belum ada bukti</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 pr-4 align-top">
                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?= badgeStatus($ord['status']) ?>">
                                                <?= htmlspecialchars($ord['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 align-top">
                                            <form method="POST" class="space-y-1.5 min-w-[165px]">
                                                <input type="hidden" name="action" value="update_order_status">
                                                <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                                
                                                <select name="payment_status" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs bg-white">
                                                    <?php foreach (['Belum Bayar', 'Menunggu Verifikasi', 'Lunas'] as $pst): ?>
                                                        <option value="<?= $pst ?>" <?= $ord['payment_status'] === $pst ? 'selected' : '' ?>>Bayar: <?= $pst ?></option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <select name="status" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs bg-white">
                                                    <?php foreach (['Menunggu Konfirmasi', 'Dikerjakan', 'Selesai', 'Dibatalkan'] as $st): ?>
                                                        <option value="<?= $st ?>" <?= $ord['status'] === $st ? 'selected' : '' ?>>Tugas: <?= $st ?></option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <button type="submit" class="w-full py-1.5 rounded-lg bg-slate-900 text-white text-xs font-bold hover:bg-indigo-600 transition">Update Status</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <!-- ================= DASHBOARD PEMESAN / KLIEN ================= -->
            <section class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-5">
                <h2 class="text-lg font-extrabold text-slate-900">Riwayat Pesanan, Bukti Bayar &amp; Ulasan (<?= count($myOrders) ?>)</h2>

                <?php if (empty($myOrders)): ?>
                    <div class="text-center py-10 space-y-3">
                        <p class="text-sm text-slate-500">Kamu belum pernah memesan layanan jasa apa pun.</p>
                        <a href="index.php" class="inline-block px-5 py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-bold">Cari Layanan Sekarang</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($myOrders as $ord): ?>
                            <div class="rounded-2xl border border-slate-200 p-5 flex flex-col justify-between gap-4 bg-slate-50/50">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?= badgeStatus($ord['payment_status']) ?>">
                                            Bayar: <?= htmlspecialchars($ord['payment_status']) ?>
                                        </span>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?= badgeStatus($ord['status']) ?>">
                                            Tugas: <?= htmlspecialchars($ord['status']) ?>
                                        </span>
                                    </div>

                                    <div>
                                        <h3 class="text-base font-bold text-slate-900"><?= htmlspecialchars($ord['service_title']) ?></h3>
                                        <p class="text-xs text-slate-500 mt-0.5">Penyedia: <strong><?= htmlspecialchars($ord['provider_name']) ?></strong> · Deadline: <strong><?= date('d M Y', strtotime($ord['deadline'])) ?></strong></p>
                                    </div>

                                    <div class="p-3 rounded-xl bg-white border border-slate-200/80 text-xs text-slate-600">
                                        <strong>Catatan Pesanan:</strong><br>
                                        <?= nl2br(htmlspecialchars($ord['notes'])) ?>
                                    </div>

                                    <!-- Bagian Upload Bukti Pembayaran -->
                                    <?php if ($ord['status'] !== 'Dibatalkan'): ?>
                                        <div class="p-3.5 rounded-xl bg-indigo-50/60 border border-indigo-100 space-y-2">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-indigo-950">Bukti Pembayaran (<?= formatRupiah($ord['total_price']) ?>)</span>
                                                <?php if (!empty($ord['payment_proof'])): ?>
                                                    <a href="<?= htmlspecialchars($ord['payment_proof']) ?>" target="_blank" class="text-[11px] font-bold text-indigo-600 underline">Lihat Bukti ↗</a>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($ord['payment_status'] !== 'Lunas'): ?>
                                                <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                                                    <input type="hidden" name="action" value="upload_payment">
                                                    <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                                    <input type="file" name="payment_proof" required accept=".jpg,.jpeg,.png,.webp" class="flex-1 text-[11px] text-slate-600">
                                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 shrink-0">Upload</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Bagian Rating & Ulasan (Muncul hanya jika status Selesai) -->
                                    <?php if ($ord['status'] === 'Selesai'): ?>
                                        <div class="p-3.5 rounded-xl bg-amber-50/80 border border-amber-200/80 space-y-2">
                                            <?php if (!empty($ord['user_rating'])): ?>
                                                <p class="text-xs font-bold text-amber-900">Ulasan Kamu: <?= str_repeat('★', (int)$ord['user_rating']) ?></p>
                                                <p class="text-xs text-slate-700 italic">"<?= htmlspecialchars($ord['user_comment']) ?>"</p>
                                            <?php else: ?>
                                                <p class="text-xs font-bold text-amber-950">Tugas Selesai! Beri Rating &amp; Ulasan Penyedia Jasa:</p>
                                                <form method="POST" class="space-y-2">
                                                    <input type="hidden" name="action" value="submit_review">
                                                    <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                                    <input type="hidden" name="service_id" value="<?= (int)$ord['service_id'] ?>">
                                                    <select name="rating" class="w-full px-3 py-1.5 rounded-lg border border-amber-300 text-xs bg-white font-semibold">
                                                        <option value="5">★★★★★ (5 - Sangat Puas &amp; Tepat Waktu)</option>
                                                        <option value="4">★★★★☆ (4 - Bagus)</option>
                                                        <option value="3">★★★☆☆ (3 - Cukup)</option>
                                                        <option value="2">★★☆☆☆ (2 - Kurang)</option>
                                                        <option value="1">★☆☆☆☆ (1 - Mengecewakan)</option>
                                                    </select>
                                                    <textarea name="comment" rows="2" required placeholder="Tulis pengalamanmu menggunakan jasa ini..." class="w-full px-3 py-1.5 rounded-lg border border-amber-300 text-xs bg-white"></textarea>
                                                    <button type="submit" class="w-full py-2 rounded-lg bg-amber-500 text-slate-900 font-bold text-xs hover:bg-amber-400 transition">Kirim Ulasan</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center justify-between pt-3 border-t border-slate-200/70">
                                    <span class="text-sm font-extrabold text-slate-900"><?= formatRupiah($ord['total_price']) ?></span>
                                    <div class="flex items-center gap-2">
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $ord['provider_wa']) ?>" target="_blank" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition">WhatsApp Penyedia</a>
                                        <?php if ($ord['status'] === 'Menunggu Konfirmasi'): ?>
                                            <form method="POST" onsubmit="return confirm('Batalkan pesanan ini?')">
                                                <input type="hidden" name="action" value="cancel_order">
                                                <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-100 text-rose-700 text-xs font-bold hover:bg-rose-200 transition">Batalkan</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    </main>
</body>
</html>