<?php
// === DEBUGGING (tampilkan error PHP jika ada) ===
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Baca data dari file JSON
$json_path = __DIR__ . '/knowledge-export.json';
if (!file_exists($json_path)) {
    $knowledge_data = ['count' => 0, 'data' => [], 'last_updated' => date('Y-m-d H:i:s')];
} else {
    $json_content = file_get_contents($json_path);
    $knowledge_data = json_decode($json_content, true);
    if (!$knowledge_data) {
        $knowledge_data = ['count' => 0, 'data' => [], 'last_updated' => date('Y-m-d H:i:s')];
    }
}

$total_records = $knowledge_data['count'];
$data = $knowledge_data['data'];
$last_updated = $knowledge_data['last_updated'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Knowledge Base Luna – Indokasino</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="index, follow">
  <meta name="description" content="Kumpulan pertanyaan & jawaban yang dipahami oleh AI Luna di Indokasino.">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, sans-serif;
      margin: 40px;
      background: #f8f9fa;
      color: #333;
    }
    h1 {
      font-size: 26px;
      color: #222;
    }
    .faq {
      margin-bottom: 30px;
      padding: 20px;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    .faq h2 {
      margin: 0 0 10px;
      font-size: 20px;
      color: #2c3e50;
    }
    .faq p {
      font-size: 16px;
      color: #555;
      margin: 0;
      line-height: 1.6;
    }
    hr {
      border: none;
      height: 1px;
      background: #ddd;
      margin: 20px 0;
    }
    .source {
      font-size: 14px;
      color: #999;
      margin-top: 40px;
    }
    .stats {
      background: #e9f7fe;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      border-left: 4px solid #3498db;
    }
    .update-info {
      font-size: 12px;
      color: #888;
      text-align: right;
      margin-top: 5px;
    }
  </style>
</head>
<body>

<h1>🧠 Knowledge Base Luna – Indokasino</h1>
<p>Berikut ini adalah kumpulan pertanyaan & jawaban resmi yang dipahami oleh AI Luna.</p>

<div class="stats">
  <p>📊 Jumlah data: <?php echo $total_records; ?> pertanyaan & jawaban</p>
  <p class="update-info">Terakhir diperbarui: <?php echo $last_updated; ?></p>
</div>

<hr>

<?php
$found = false;
foreach ($data as $row) {
  $question = trim($row['question']);
  $answer = trim($row['answer']);
  if ($question && $answer) {
    $found = true;
    echo '<div class="faq">';
    echo '<h2>❓ ' . htmlspecialchars($question) . '</h2>';
    echo '<p>' . nl2br(htmlspecialchars($answer)) . '</p>';
    echo '</div>';
  }
}
if (!$found) {
  echo "<p><i>Tidak ada data tersedia dalam Knowledge Base.</i></p>";
}
?>

<div class="source">
  <p>📌 Sumber data: Luna Database &mdash; Diupdate otomatis setiap kali Luna menjawab pertanyaan.</p>
</div>

</body>
</html>
