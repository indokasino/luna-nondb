<?php
/**
 * Script validasi database & integritas data
 * Gunakan untuk memeriksa koneksi database, integritas tabel, dan validitas data
 * 
 * Cara penggunaan:
 * 1. Simpan file ini di direktori root aplikasi
 * 2. Jalankan melalui browser atau command line: php db_validator.php
 */

// Ambil konfigurasi dari file config
$config = require __DIR__ . '/config/config.php';
$dbPath = $config['db_path'];

echo "======= VALIDATOR DATABASE LUNA =======\n";
echo "Mulai validasi database...\n";

// 1. Cek keberadaan file database
if (!file_exists($dbPath)) {
    echo "❌ ERROR: File database tidak ditemukan di: $dbPath\n";
    echo "Solusi: Jalankan setup_database.php untuk membuat database\n";
    exit(1);
}

try {
    // 2. Cek koneksi database
    echo "Memeriksa koneksi database...\n";
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Koneksi database berhasil\n";
    
    // 3. Cek struktur tabel
    echo "\nMemeriksa struktur tabel...\n";
    $tables = ['conversations', 'knowledge_base', 'feedback_scores'];
    $missingTables = [];
    
    foreach ($tables as $table) {
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if (!$result->fetch()) {
            $missingTables[] = $table;
        }
    }
    
    if (count($missingTables) > 0) {
        echo "❌ Tabel yang tidak ditemukan: " . implode(", ", $missingTables) . "\n";
        echo "Solusi: Jalankan setup_database.php untuk membuat tabel\n";
    } else {
        echo "✅ Semua tabel ditemukan\n";
    }
    
    // 4. Cek jumlah data
    echo "\nMemeriksa jumlah data...\n";
    
    // Cek conversations
    $result = $db->query("SELECT COUNT(*) as count FROM conversations");
    $conversationsCount = $result->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Jumlah data conversations: $conversationsCount\n";
    
    // Cek knowledge_base
    $result = $db->query("SELECT COUNT(*) as count FROM knowledge_base");
    $knowledgeCount = $result->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Jumlah data knowledge_base: $knowledgeCount\n";
    
    // Cek feedback_scores
    $result = $db->query("SELECT COUNT(*) as count FROM feedback_scores");
    $feedbackCount = $result->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Jumlah data feedback_scores: $feedbackCount\n";
    
    // 5. Cek data kosong di knowledge_base
    $result = $db->query("SELECT COUNT(*) as count FROM knowledge_base WHERE question = '' OR answer = ''");
    $emptyCount = $result->fetch(PDO::FETCH_ASSOC)['count'];
    if ($emptyCount > 0) {
        echo "❌ Terdapat $emptyCount data kosong di knowledge_base\n";
        echo "Solusi: Jalankan knowledge_cleanup.php untuk membersihkan data\n";
    } else {
        echo "✅ Tidak ditemukan data kosong di knowledge_base\n";
    }
    
    // 6. Cek data duplikat di knowledge_base
    $result = $db->query("
        SELECT question, COUNT(*) as count 
        FROM knowledge_base 
        GROUP BY question 
        HAVING COUNT(*) > 1
    ");
    $duplicateData = $result->fetchAll(PDO::FETCH_ASSOC);
    if (count($duplicateData) > 0) {
        echo "❓ Terdapat " . count($duplicateData) . " pertanyaan duplikat di knowledge_base\n";
        echo "Pertimbangkan untuk menjalankan knowledge_cleanup.php\n";
    } else {
        echo "✅ Tidak ditemukan pertanyaan duplikat di knowledge_base\n";
    }
    
    // 7. Cek integritas relasi
    $result = $db->query("
        SELECT COUNT(*) as count 
        FROM feedback_scores f 
        LEFT JOIN conversations c ON f.conversation_id = c.id 
        WHERE c.id IS NULL
    ");
    $orphanCount = $result->fetch(PDO::FETCH_ASSOC)['count'];
    if ($orphanCount > 0) {
        echo "❌ Terdapat $orphanCount data feedback tanpa percakapan terkait\n";
        echo "Solusi: Jalankan query perbaikan untuk menghapus data orphan\n";
    } else {
        echo "✅ Integritas relasi antar tabel baik\n";
    }
    
    // 8. Cek sampling data knowledge_base
    if ($knowledgeCount > 0) {
        echo "\nSample data knowledge_base (5 terakhir):\n";
        $result = $db->query("SELECT id, question, substr(answer, 1, 50) as answer_preview, created_at FROM knowledge_base ORDER BY id DESC LIMIT 5");
        $samples = $result->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($samples as $sample) {
            echo "ID: " . $sample['id'] . "\n";
            echo "Question: " . $sample['question'] . "\n";
            echo "Answer Preview: " . $sample['answer_preview'] . "...\n";
            echo "Created: " . $sample['created_at'] . "\n";
            echo "----------\n";
        }
    }
    
    // 9. Cek fungsi knowledge-article.php
    echo "\nValidasi knowledge-article.php...\n";
    if (file_exists(__DIR__ . '/knowledge-article.php')) {
        echo "✅ File knowledge-article.php ditemukan\n";
    } else {
        echo "❌ File knowledge-article.php tidak ditemukan\n";
    }
    
    echo "\n======= VALIDASI SELESAI =======\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR DATABASE: " . $e->getMessage() . "\n";
    exit(1);
}