<?php
/**
 * Script untuk mengekspor data dari SQLite ke file JSON
 * Jalankan script ini secara manual atau via cron
 */

// Path ke database SQLite
$db_path = __DIR__ . '/database/luna.sqlite';

try {
    echo "Mulai mengekspor data knowledge base ke JSON...\n";
    
    // Buat koneksi ke database
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set busy timeout untuk menghindari database locked
    $db->exec('PRAGMA busy_timeout = 10000;');
    
    // Ambil semua data dari knowledge_base
    $results = $db->query("SELECT question, answer FROM knowledge_base ORDER BY id DESC");
    
    // Fetch semua data sebagai array
    $data = $results->fetchAll(PDO::FETCH_ASSOC);
    $count = count($data);
    
    // Buat struktur data untuk diekspor
    $export = [
        'count' => $count,
        'data' => $data,
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    // Simpan ke file JSON
    $json_path = __DIR__ . '/knowledge-export.json';
    file_put_contents($json_path, json_encode($export, JSON_PRETTY_PRINT));
    
    echo "Berhasil mengekspor $count data ke knowledge-export.json\n";
    echo "File disimpan di: $json_path\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}