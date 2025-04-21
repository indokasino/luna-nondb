<?php
// === DEBUGGING (tampilkan error PHP jika ada) ===
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Baca dari file JSON alih-alih database
$json_path = __DIR__ . '/conversations-export.json';
if (!file_exists($json_path)) {
    die("File conversations-export.json tidak ditemukan.\nJalankan dahulu: php export-conversations.php");
}

try {
    $json_content = file_get_contents($json_path);
    $data = json_decode($json_content, true);
    
    if (!$data) {
        die("Format JSON tidak valid.");
    }
    
    echo "===== REPORT CONVERSATIONS DATABASE =====\n";
    echo "Last Updated: " . $data['last_updated'] . "\n\n";
    
    echo "===== 10 DATA TERAKHIR DI CONVERSATIONS =====\n";
    foreach ($data['recent'] as $row) {
        echo "ID: " . $row['id'] . "\n";
        echo "Question: " . $row['user_message'] . "\n";
        echo "Answer Preview: " . $row['preview'] . "...\n";
        echo "Created: " . $row['created_at'] . "\n";
        echo "----------\n";
    }
    
    echo "\nTotal data: " . $data['total'] . "\n";
    echo "Total data yang berpotensi untuk knowledge_base: " . $data['qualified'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}