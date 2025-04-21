<?php
/**
 * Script untuk membersihkan dan mengoptimalkan knowledge base
 * - Menghapus duplikasi pertanyaan
 * - Menghapus data yang tidak berguna
 * 
 * Jalankan script ini secara berkala menggunakan cron job
 * Contoh: 0 3 * * * php /path/to/includes/knowledge_cleanup.php
 */

require_once __DIR__ . '/database.php';
$config = require __DIR__ . '/../config/config.php';

try {
    echo "Mulai membersihkan knowledge base...\n";
    
    $db = new LunaDB($config['db_path']);
    
    // Hitung total record sebelum
    $pdo = new PDO('sqlite:' . $config['db_path']);
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM knowledge_base");
    $before = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "Jumlah data sebelum pembersihan: $before\n";
    
    // Bersihkan knowledge base
    $db->cleanupKnowledgeBase();
    
    // Hitung total record sesudah
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM knowledge_base");
    $after = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "Jumlah data setelah pembersihan: $after\n";
    echo "Berhasil menghapus " . ($before - $after) . " record duplikat atau tidak berguna.\n";
    
    echo "Selesai!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}