<?php
require_once 'config/database.php';

$category = $_GET['category'] ?? '';
$search   = trim($_GET['search'] ?? '');

$sql = "SELECT s.*, u.name AS provider_name, u.is_verified,
               COALESCE(ROUND(AVG(r.rating), 1), 0) AS avg_rating,
               COUNT(r.id) AS total_reviews
        FROM services s 
        JOIN users u ON s.provider_id = u.id 
        LEFT JOIN reviews r ON s.id = r.service_id
        WHERE 1=1";
$params = [];

if ($category !== '') {
    $sql .= " AND s.category = ?";
    $params[] = $category;
}
if ($search !== '') {
    $sql .= " AND (s.title LIKE ? OR s.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY s.id ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

$categories = ['Akademik & Laprak', 'Desain & PPT', 'IT & Koding', 'Jastip & Harian'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BantuKampus — Marketplace Jasa Mahasiswa Terverifikasi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <nav class="bg-white/90 backdrop-blur border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2.5 font-extrabold text-xl tracking-tight text-slate-900">
                <span class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-sm">BK</span>
                Bantu<span class="text-indigo-600">Kampus</span>
            </a>

            <div class="flex items-center gap-3">
                <?php if (isset($_SESSION['user'])): ?>
                    <a href="dashboard.php" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs sm:text-sm font-semibold hover:bg-slate-800 transition">
                        Dashboard (<?= htmlspecialchars(explode(' ', $_SESSION['user']['name'])[0]) ?>)
                    </a>
                    <a href="logout.php" class="px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold text-rose-600 hover:bg-rose-50 transition">Keluar</a>
                <?php else: ?>
                    <a href="login.php" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-100 transition">Masuk</a>
                    <a href="register.php" class="px-4 py-2 rounded-xl bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 transition shadow-sm">Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <header class="bg-gradient-to-b from-indigo-950 via-slate-900 to-slate-900 text-white py-14 sm:py-20 px-4">
        <div class="max-w-4xl mx-auto text-center space-y-5">
            <span class="inline-block px-3.5 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-200 text-xs font-semibold">
                🛡️ Penyedia Jasa Terverifikasi KTM &amp; Transaksi Aman
            </span>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight leading-tight">
                Deadline Laprak &amp; Tugas Mepet?<br>
                <span class="text-lime-400">Serahkan ke Ahlinya di Kampus.</span>
            </h1>
            <p class="text-slate-300 text-sm sm:text-base max-w-2xl mx-auto">
                Temukan jasa ketik laporan praktikum, desain PPT presentasi, perbaikan koding, hingga jastip print dari sesama mahasiswa terverifikasi.
            </p>

            <form method="GET" action="index.php" class="max-w-xl mx-auto pt-3 flex flex-col sm:flex-row gap-2">
                <?php if ($category): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                <?php endif; ?>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Cari jasa (misal: laprak, PPT, print, PHP)..." 
                       class="flex-1 px-4 py-3.5 rounded-2xl bg-white text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-lime-400">
                <button type="submit" class="px-6 py-3.5 rounded-2xl bg-lime-400 text-slate-900 font-bold text-sm hover:bg-lime-300 transition shrink-0">
                    Cari Jasa
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-10 space-y-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap gap-2">
                <a href="index.php" class="px-4 py-2 rounded-full text-xs font-semibold transition <?= $category === '' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100' ?>">
                    Semua Layanan
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="index.php?category=<?= urlencode($cat) ?>" class="px-4 py-2 rounded-full text-xs font-semibold transition <?= $category === $cat ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100' ?>">
                        <?= htmlspecialchars($cat) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <span class="text-xs font-semibold text-slate-500">Menampilkan <?= count($services) ?> layanan</span>
        </div>

        <?php if (empty($services)): ?>
            <div class="bg-white rounded-3xl p-12 text-center border border-slate-200">
                <p class="text-slate-500 text-sm">Belum ada layanan jasa yang sesuai dengan pencarianmu.</p>
                <a href="index.php" class="inline-block mt-4 px-4 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold">Reset Filter</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($services as $item): ?>
                    <article class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="px-3 py-1 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700">
                                    <?= htmlspecialchars($item['category']) ?>
                                </span>
                                <span class="text-xs font-bold text-amber-500">
                                    ★ <?= $item['total_reviews'] > 0 ? $item['avg_rating'] . " ({$item['total_reviews']})" : 'Baru' ?>
                                </span>
                            </div>

                            <h2 class="text-lg font-bold text-slate-900 leading-snug">
                                <?= htmlspecialchars($item['title']) ?>
                            </h2>

                            <div class="flex items-center gap-1.5 mt-2 text-xs text-slate-600">
                                <span class="font-semibold"><?= htmlspecialchars($item['provider_name']) ?></span>
                                <?php if ($item['is_verified']): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold">✓ Verified KTM</span>
                                <?php endif; ?>
                            </div>

                            <p class="text-xs text-slate-500 mt-3 line-clamp-3 leading-relaxed">
                                <?= htmlspecialchars($item['description']) ?>
                            </p>
                        </div>

                        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                            <div>
                                <span class="text-[11px] text-slate-400 block">Estimasi <?= (int)$item['estimate_days'] ?> Hari</span>
                                <span class="text-lg font-extrabold text-slate-900"><?= formatRupiah($item['price']) ?></span>
                            </div>
                            <a href="detail.php?id=<?= (int)$item['id'] ?>" class="px-4 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-indigo-600 transition">
                                Pesan Jasa →
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="border-t border-slate-200 bg-white py-6 text-center text-xs text-slate-400 mt-12">
        &copy; 2026 <strong>BantuKampus</strong> — Tugas Pemrograman Web · Marketplace Jasa Mahasiswa Terverifikasi
    </footer>
</body>
</html>