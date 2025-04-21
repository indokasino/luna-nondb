<?php
/**
 * Script untuk mengekspor data conversations ke JSON
 * Jalankan dari CLI: php export-conversations.php
 */

// Path ke database
$config = require __DIR__ . '/config/config.php';
$dbPath = $config['db_path'];

try {
    echo "Mulai mengekspor data conversations...\n";
    
    // Buka database
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set timeout
    $db->exec('PRAGMA busy_timeout = 30000;');
    
    // Ambil 10 data terakhir
    $result = $db->query("SELECT id, user_message, substr(ai_response, 1, 100) as preview, created_at 
                          FROM conversations 
                          ORDER BY id DESC LIMIT 10");
    
    // Ambil data ke array
    $conversations = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $conversations[] = $row;
    }
    
    // Hitung yang memenuhi syarat untuk knowledge_base
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM conversations WHERE LENGTH(user_message) >= 5 AND LENGTH(ai_response) >= 10");
    $stmt->execute();
    $qualified = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Hitung total conversations
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM conversations");
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Buat data hasil ekspor
    $export = [
        'total' => $total,
        'qualified' => $qualified,
        'recent' => $conversations,
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    // Simpan ke file JSON
    file_put_contents(__DIR__ . '/conversations-export.json', json_encode($export, JSON_PRETTY_PRINT));
    
    echo "Berhasil mengekspor data conversations ke conversations-export.json\n";
    echo "- Total data: $total\n";
    echo "- Data yang memenuhi syarat: $qualified\n";
    echo "- Data terbaru: " . count($conversations) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}