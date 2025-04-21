<?php
/**
 * Script untuk mengimpor data dari conversations ke knowledge_base
 * dengan penanganan database locked yang lebih baik
 */

// Fungsi untuk menilai kualitas percakapan (lebih fleksibel)
function isQualityConversation($question, $answer) {
    // Minimal panjang - lebih lenient
    if (strlen($question) < 5 || strlen($answer) < 10) {
        return false;
    }
    
    // Hindari pertanyaan pendek
    $shortQuestions = ['ok', 'ya', 'tidak', 'gak', 'ga', 'oke', 'sip', 'thx'];
    foreach ($shortQuestions as $q) {
        if (strtolower(trim($question)) === $q) {
            return false;
        }
    }
    
    // Minimal jumlah kata (lebih fleksibel)
    if (str_word_count($question) < 1) {
        return false;
    }
    
    if (str_word_count($answer) < 5) {
        return false;
    }
    
    return true;
}

try {
    echo "Mulai mengimpor data dengan penanganan lock yang lebih baik...\n";
    
    // Path ke database
    $dbPath = __DIR__ . '/database/luna.sqlite';
    if (!file_exists($dbPath)) {
        die("Database tidak ditemukan di: $dbPath\n");
    }
    
    // Buat koneksi langsung ke database tanpa menggunakan class LunaDB
    // untuk menghindari konflik lock
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set busy timeout yang lebih panjang dan WAL mode
    $pdo->exec('PRAGMA busy_timeout = 30000;'); // 30 detik
    $pdo->exec('PRAGMA journal_mode = WAL;');   // Write-Ahead Logging
    
    // 1. Ambil conversations yang berpotensi - TANPA FILTER TANGGAL
    echo "Mengambil data dari conversations...\n";
    
    $stmt = $pdo->query("
        SELECT id, user_message, ai_response, created_at 
        FROM conversations 
        ORDER BY id DESC 
        LIMIT 500
    ");
    
    // 2. Siapkan data untuk import batch
    $validConversations = [];
    $total = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $total++;
        
        if (isQualityConversation($row['user_message'], $row['ai_response'])) {
            $validConversations[] = [
                'question' => $row['user_message'],
                'answer' => $row['ai_response']
            ];
        }
    }
    
    echo "Ditemukan " . count($validConversations) . " dari $total conversations yang memenuhi syarat.\n";
    
    // 3. Import data ke knowledge_base
    if (count($validConversations) > 0) {
        echo "Memulai proses import batch ke knowledge_base...\n";
        
        // Lakukan dalam transaksi untuk mempercepat
        $pdo->beginTransaction();
        
        $imported = 0;
        foreach ($validConversations as $conv) {
            // Cek apakah pertanyaan sudah ada
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM knowledge_base WHERE question = ?");
            $checkStmt->execute([$conv['question']]);
            
            if ($checkStmt->fetchColumn() == 0) {
                // Tambahkan ke knowledge_base jika belum ada
                try {
                    $insertStmt = $pdo->prepare("INSERT INTO knowledge_base (question, answer) VALUES (?, ?)");
                    $insertStmt->execute([$conv['question'], $conv['answer']]);
                    $imported++;
                } catch (Exception $e) {
                    echo "Gagal menyimpan: " . substr($conv['question'], 0, 30) . "...\n";
                    continue;
                }
            }
        }
        
        // Commit transaksi
        $pdo->commit();
        
        echo "Berhasil mengimpor $imported data baru ke knowledge_base.\n";
    }
    
    // 4. Bersihkan duplicates jika ada
    echo "Membersihkan data duplikat...\n";
    $pdo->exec("
        DELETE FROM knowledge_base
        WHERE id NOT IN (
            SELECT MAX(id) FROM knowledge_base
            GROUP BY question
        )
    ");
    
    // 5. Hitung hasil akhir
    $countStmt = $pdo->query("SELECT COUNT(*) FROM knowledge_base");
    $finalCount = $countStmt->fetchColumn();
    
    echo "Proses selesai. Total data di knowledge_base: $finalCount\n";
    
    // Tutup koneksi
    $stmt = null;
    $pdo = null;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    
    // Rollback jika dalam transaksi
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    exit(1);
}