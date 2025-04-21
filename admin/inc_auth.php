<?php
// ──────────────────────────────────────────────────────────
// LUNA AI Chatbot - Admin Panel
// Struktur folder: /admin
//   inc_auth.php   : Middleware autentikasi
//   index.php      : Dashboard - List & CRUD Knowledge Base
//   kb_edit.php    : Form Tambah/Edit Q&A
//   settings.php   : Form CRUD Settings
// ──────────────────────────────────────────────────────────

/*
1. Buat migrasi SQL di database/migrations/create_settings_table.sql:

-- BEGIN create_settings_table.sql
CREATE TABLE IF NOT EXISTS settings (
  `k` VARCHAR(100) PRIMARY KEY,
  `v` TEXT NOT NULL
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- END
*/

/*
2. Update config/config.php untuk load settings:
*/
// setelah load env dan sebelum return $config:
$pdo = getPDOConnection();
$stmt = $pdo->query("SELECT `k`,`v` FROM settings");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $config['custom'][$row['k']] = $row['v'];
}

return $config;

?>

<?php /* File: /admin/inc_auth.php */ ?>
<?php
session_start();
require __DIR__ . '/../config/config.php';

// Kredensial di .env: ADMIN_USER dan ADMIN_PASS
$userEnv = getenv('ADMIN_USER');\$passEnv = getenv('ADMIN_PASS');
if (!isset(\$_SESSION['admin']) || \$_SESSION['admin'] !== true) {
    if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
        \$u = \$_POST['user'] ?? '';
        \$p = \$_POST['pass'] ?? '';
        if (\$u === \$userEnv && \$p === \$passEnv) {
            \$_SESSION['admin'] = true;
            header('Location: index.php'); exit;
        } else {
            \$error = 'Login gagal';
        }
    }
    echo '<!doctype html><html><head><title>Login Admin</title></head><body>';
    if (!empty(\$error)) echo "<p style='color:red;'>\$error</p>";
    echo '<form method="post">',
         'User: <input name="user"><br>',
         'Pass: <input type="password" name="pass"><br>',
         '<button>Login</button>',
         '</form></body></html>';
    exit;
}
?>

<?php /* File: /admin/index.php */ ?>
<?php
require 'inc_auth.php';
require __DIR__ . '/../includes/database.php';
\$db = getPDOConnection();

// Hapus jika ada aksi delete
if (isset(\$_GET['delete'])) {
    \$stmt = \$db->prepare("DELETE FROM knowledge_base WHERE id = ?");
    \$stmt->execute([ (int)\$_GET['delete'] ]);
    header('Location: index.php'); exit;
}

// Ambil semua KB
\$rows = \$db->query("SELECT id, question, answer, created_at FROM knowledge_base ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html><head><title>Admin Panel - Knowledge Base</title></head><body>
<h1>Knowledge Base</h1>
<a href="kb_edit.php">+ Tambah Q&A</a> | <a href="settings.php">Settings</a> | <a href="?logout=1">Logout</a>
<table border="1" cellpadding="5">
  <tr><th>ID</th><th>Pertanyaan</th><th>Jawaban</th><th>Waktu</th><th>Aksi</th></tr>
  <?php foreach (\$rows as \$r): ?>
    <tr>
      <td><?= \$r['id'] ?></td>
      <td><?= htmlspecialchars(\$r['question']) ?></td>
      <td><?= htmlspecialchars(\$r['answer']) ?></td>
      <td><?= \$r['created_at'] ?></td>
      <td>
        <a href="kb_edit.php?id=<?= \$r['id'] ?>">Edit</a>
        <a href="?delete=<?= \$r['id'] ?>" onclick="return confirm('Hapus?')">Hapus</a>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
</body></html>

<?php /* File: /admin/kb_edit.php */ ?>
<?php
require 'inc_auth.php';
require __DIR__ . '/../includes/database.php';
\$db = getPDOConnection();

// Load data jika edit
\$item = ['id'=>0,'question'=>'','answer'=>''];
if (!empty(\$_GET['id'])) {
    \$stmt = \$db->prepare("SELECT * FROM knowledge_base WHERE id = ?");
    \$stmt->execute([ (int)\$_GET['id'] ]);
    \$item = \$stmt->fetch(PDO::FETCH_ASSOC) ?: \$item;
}

// Proses form
if (\$_SERVER['REQUEST_METHOD']==='POST') {
    \$q = \$_POST['question']; \$a = \$_POST['answer'];
    if (!empty(\$_POST['id'])) {
        \$stmt = \$db->prepare("UPDATE knowledge_base SET question=?,answer=? WHERE id=?");
        \$stmt->execute([\$q,\$a,(int)\$_POST['id']]);
    } else {
        \$stmt = \$db->prepare("INSERT INTO knowledge_base(question,answer) VALUES(?,?)");
        \$stmt->execute([\$q,\$a]);
    }
    header('Location: index.php'); exit;
}
?>
<!doctype html><html><head><title>Tambah/Edit Q&A</title></head><body>
<h1><?= \$item['id'] ? 'Edit' : 'Tambah' ?> Q&A</h1>
<form method="post">
  <input type="hidden" name="id" value="<?= \$item['id'] ?>">
  Pertanyaan:<br><textarea name="question" rows="3" cols="60"><?= htmlspecialchars(\$item['question']) ?></textarea><br>
  Jawaban:<br><textarea name="answer" rows="5" cols="60"><?= htmlspecialchars(\$item['answer']) ?></textarea><br>
  <button>Simpan</button>
</form>
<a href="index.php">← Kembali</a>
</body></html>

<?php /* File: /admin/settings.php */ ?>
<?php
require 'inc_auth.php';
require __DIR__ . '/../includes/database.php';
\$db = getPDOConnection();

// Proses simpan
if (\$_SERVER['REQUEST_METHOD']==='POST') {
    foreach (\$_POST as \$k=>\$v) {
        \$stmt = \$db->prepare(
          "INSERT INTO settings(`k`,`v`) VALUES(?,?) " .
          "ON DUPLICATE KEY UPDATE v=VALUES(v)"
        );
        \$stmt->execute([\$k,\$v]);
    }
    header('Location: settings.php'); exit;
}

// Load settings
\$rows = \$db->query("SELECT `k`,`v` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

// Default keys
\$defaults = ['threshold_embedding'=>'0.25','cache_enabled'=>'0','cleanup_freq'=>'7'];
?>
<!doctype html><html><head><title>Settings</title></head><body>
<h1>Pengaturan</h1>
<form method="post">
  <?php foreach (\$defaults as \$k=>\$d): ?>
    <label><?= \$k ?>:</label>
    <input name="<?= \$k ?>" value="<?= htmlspecialchars(\$rows[\$k]??\$d) ?>"><br>
  <?php endforeach; ?>
  <button>Simpan Pengaturan</button>
</form>
<a href="index.php">← Kembali</a>
</body></html>
