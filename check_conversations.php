<?php
// Static version that doesn't access database
// === DEBUGGING (tampilkan error PHP jika ada) ===
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "===== REPORT CONVERSATIONS DATABASE =====\n";
echo "Status: Database dalam mode read-only\n";
echo "\nUntuk melihat data terbaru, gunakan export dan baca dari file JSON.\n";
echo "Langkah-langkah:\n";
echo "1. Jalankan: php export-conversations.php\n";
echo "2. Baca data dari file JSON yang dihasilkan\n";
echo "\nInformasi ini bersifat sementara sampai masalah akses database teratasi.\n";