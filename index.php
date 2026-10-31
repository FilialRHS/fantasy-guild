<?php
// Konfigurasi database dibaca dari Environment variables / App settings di Azure.
$host = getenv('DB_HOST') ?: 'localhost';
$name = getenv('DB_NAME') ?: 'guild';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $db = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Azure MySQL mewajibkan koneksi SSL
        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ]);
} catch (PDOException $ex) {
    http_response_code(500);
    exit('Gagal terhubung ke database. Cek DB_HOST, DB_NAME, DB_USER, DB_PASS dan firewall MySQL.');
}

$db->exec("CREATE TABLE IF NOT EXISTS heroes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    job VARCHAR(30) NOT NULL,
    level INT NOT NULL DEFAULT 1,
    item VARCHAR(100) NOT NULL
) ENGINE=InnoDB");

$daftarJob = ['Warrior', 'Mage', 'Archer', 'Healer', 'Rogue'];

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ===== CREATE, UPDATE, DELETE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $nama   = trim($_POST['nama'] ?? '');
    $job    = in_array($_POST['job'] ?? '', $daftarJob, true) ? $_POST['job'] : '';
    $level  = max(1, (int)($_POST['level'] ?? 1));
    $item   = trim($_POST['item'] ?? '');

    if ($action === 'create' && $nama !== '' && $job !== '' && $item !== '') {
        $stmt = $db->prepare("INSERT INTO heroes (nama, job, level, item) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nama, $job, $level, $item]);
    } elseif ($action === 'update' && $id > 0 && $nama !== '' && $job !== '' && $item !== '') {
        $stmt = $db->prepare("UPDATE heroes SET nama = ?, job = ?, level = ?, item = ? WHERE id = ?");
        $stmt->execute([$nama, $job, $level, $item, $id]);
    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $db->prepare("DELETE FROM heroes WHERE id = ?");
        $stmt->execute([$id]);
    }

    header('Location: index.php');
    exit;
}

// ===== READ =====
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM heroes WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$rows = $db->query("SELECT * FROM heroes ORDER BY level DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fantasy Guild</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Georgia, serif; background: #1f1b2e; margin: 0; padding: 24px; color: #eee; }
        .wrap { max-width: 820px; margin: 0 auto; }
        h1 { text-align: center; color: #f4c95d; letter-spacing: 1px; }
        .sub { text-align: center; color: #aaa; margin-top: -10px; margin-bottom: 24px; }
        .card { background: #2b2640; border: 1px solid #4a4268; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        h3 { margin-top: 0; color: #f4c95d; }
        form.input { display: flex; gap: 8px; flex-wrap: wrap; }
        input, select { padding: 10px; border: 1px solid #4a4268; border-radius: 6px; background: #1f1b2e; color: #eee; flex: 1; min-width: 120px; }
        button, .btn { padding: 10px 14px; border: 0; border-radius: 6px; cursor: pointer; color: #1f1b2e; text-decoration: none; font-size: 14px; font-weight: bold; }
        .primary { background: #f4c95d; }
        .warn { background: #8ecae6; }
        .danger { background: #e76f51; color: #fff; }
        .batal { background: #888; color: #fff; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #3c3558; }
        th { color: #f4c95d; }
        .aksi { display: flex; gap: 6px; }
        .aksi form { margin: 0; }
        .kosong { text-align: center; color: #999; padding: 20px; }
        .total { text-align: right; color: #aaa; font-size: 14px; margin-top: 10px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Fantasy Guild</h1>
    <p class="sub">Daftar pahlawan, job, dan item andalannya</p>

    <div class="card">
        <h3><?= $edit ? 'Ubah Pahlawan' : 'Rekrut Pahlawan Baru' ?></h3>
        <form method="post" class="input">
            <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
            <?php if ($edit): ?>
                <input type="hidden" name="id" value="<?= e($edit['id']) ?>">
            <?php endif; ?>
            <input type="text" name="nama" placeholder="Nama pahlawan" required value="<?= e($edit['nama'] ?? '') ?>">
            <select name="job" required>
                <option value="">Pilih job</option>
                <?php foreach ($daftarJob as $j): ?>
                    <option value="<?= e($j) ?>" <?= (($edit['job'] ?? '') === $j) ? 'selected' : '' ?>><?= e($j) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="level" placeholder="Level" min="1" required value="<?= e($edit['level'] ?? 1) ?>">
            <input type="text" name="item" placeholder="Item (mis. Pedang Api)" required value="<?= e($edit['item'] ?? '') ?>">
            <button type="submit" class="primary"><?= $edit ? 'Simpan' : 'Rekrut' ?></button>
            <?php if ($edit): ?>
                <a href="index.php" class="btn batal">Batal</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3>Anggota Guild</h3>
        <table>
            <tr><th>#</th><th>Nama</th><th>Job</th><th>Level</th><th>Item</th><th>Aksi</th></tr>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="kosong">Guild masih kosong. Rekrut pahlawan pertamamu!</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($r['nama']) ?></td>
                    <td><?= e($r['job']) ?></td>
                    <td><?= e($r['level']) ?></td>
                    <td><?= e($r['item']) ?></td>
                    <td class="aksi">
                        <a href="?edit=<?= e($r['id']) ?>" class="btn warn">Ubah</a>
                        <form method="post" onsubmit="return confirm('Hapus pahlawan ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                            <button type="submit" class="danger">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <div class="total">Total anggota: <?= count($rows) ?></div>
    </div>
</div>
</body>
</html>