<?php
// Path root dari aplikasi
$root_path = __DIR__; // Ini akan menghasilkan /home/admin/web/database.berbagitutorial.info/public_html/luna-indokasino

// Path ke database - perbaiki untuk tidak ada duplikasi "database"
$db_path = $root_path . '/database/luna.sqlite';

// Path ke file SQL - perbaiki untuk tidak ada duplikasi "database" 
$sql_path = $root_path . '/database/migrations/create_tables.sql';

echo "Memulai setup database...\n";
echo "Root path: $root_path\n";

// Pastikan direktori database ada
$db_dir = dirname($db_path);
if (!is_dir($db_dir)) {
    echo "Membuat direktori database: $db_dir\n";
    mkdir($db_dir, 0755, true);
}

// Pastikan direktori migrations ada
$migrations_dir = dirname($sql_path);
if (!is_dir($migrations_dir)) {
    echo "Membuat direktori migrations: $migrations_dir\n";
    mkdir($migrations_dir, 0755, true);
}

try {
    // Buat koneksi ke database (akan membuat file baru jika belum ada)
    echo "Membuat koneksi ke database: $db_path\n";
    $db = new SQLite3($db_path);
    
    // Cek apakah file SQL ada
    echo "Memeriksa file SQL: $sql_path\n";
    if (!file_exists($sql_path)) {
        echo "File SQL tidak ditemukan, membuat file baru...\n";
        
        // Buat konten SQL
        $sql_content = "-- Pastikan struktur database sudah ada tabel knowledge_base
CREATE TABLE IF NOT EXISTS conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_message TEXT NOT NULL,
    ai_response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS knowledge_base (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS feedback_scores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    score REAL NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
);

-- Membuat indeks untuk mempercepat pencarian
CREATE INDEX IF NOT EXISTS idx_knowledge_question ON knowledge_base(question);
CREATE INDEX IF NOT EXISTS idx_conversations_created ON conversations(created_at);";
        
        // Buat direktori migrations jika belum ada
        if (!is_dir(dirname($sql_path))) {
            mkdir(dirname($sql_path), 0755, true);
        }
        
        // Tulis konten ke file
        file_put_contents($sql_path, $sql_content);
        echo "File SQL berhasil dibuat di: $sql_path\n";
    }
    
    // Baca isi file SQL
    echo "Membaca file SQL...\n";
    $sql = file_get_contents($sql_path);
    
    // Jalankan perintah SQL
    echo "Menjalankan perintah SQL untuk membuat tabel...\n";
    // Gunakan exec untuk statement individual
    $statements = explode(';', $sql);
    
    foreach($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $result = $db->exec($statement);
            if ($result === false) {
                echo "Warning: Possible issue with SQL statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    echo "Setup database berhasil!\n";
    
    // Cek tabel yang berhasil dibuat
    $tables = [];
    $res = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $tables[] = $row['name'];
    }
    
    if (count($tables) > 0) {
        echo "Tabel yang berhasil dibuat: " . implode(", ", $tables) . "\n";
    } else {
        echo "Peringatan: Tidak ada tabel yang terbentuk. Periksa file SQL.\n";
    }
    
    $db->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}