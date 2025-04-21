<?php

class LunaDB {
    private $pdo;

    public function __construct($dbPath) {
        if (!file_exists($dbPath)) {
            throw new Exception("Database tidak ditemukan di path: $dbPath");
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set busy timeout dan WAL mode untuk performa dan menghindari database locked
        $this->pdo->exec('PRAGMA busy_timeout = 30000;'); // 30 detik timeout
        $this->pdo->exec('PRAGMA journal_mode = WAL;');   // Write-Ahead Logging
        
        // Ensure the database has the required tables
        $this->initializeTables();
    }
    
    // Initialize tables if they don't exist
    private function initializeTables() {
        // Create conversations table if not exists
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_message TEXT NOT NULL,
            ai_response TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create knowledge_base table if not exists
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_base (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create feedback_scores table if not exists
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS feedback_scores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER NOT NULL,
            score REAL NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        )");
        
        // Create indexes
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_knowledge_question ON knowledge_base(question)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_conversations_created ON conversations(created_at)");
    }

    // Simpan log percakapan
    public function saveConversation($userMessage, $aiResponse) {
        $stmt = $this->pdo->prepare("INSERT INTO conversations (user_message, ai_response) VALUES (?, ?)");
        $stmt->execute([$userMessage, $aiResponse]);
        return $this->pdo->lastInsertId();
    }

    // Simpan skor feedback
    public function saveFeedback($conversationId, $score, $notes = null) {
        $stmt = $this->pdo->prepare("INSERT INTO feedback_scores (conversation_id, score, notes) VALUES (?, ?, ?)");
        $stmt->execute([$conversationId, $score, $notes]);
        
        // Jika feedback score bagus, tambahkan ke knowledge base
        if ($score >= 4.0) {
            $stmt = $this->pdo->prepare("
                SELECT user_message, ai_response 
                FROM conversations 
                WHERE id = ?
            ");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conversation) {
                $this->addKnowledge($conversation['user_message'], $conversation['ai_response']);
            }
        }
    }

    // Tambahkan data ke knowledge base
    public function addKnowledge($question, $answer) {
        try {
            // Periksa apakah sudah ada pertanyaan yang sama
            $stmt = $this->pdo->prepare("SELECT id FROM knowledge_base WHERE question = ? LIMIT 1");
            $stmt->execute([$question]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update jawaban jika pertanyaan sudah ada
                $stmt = $this->pdo->prepare("UPDATE knowledge_base SET answer = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$answer, $existing['id']]);
            } else {
                // Insert baru jika pertanyaan belum ada
                $stmt = $this->pdo->prepare("INSERT INTO knowledge_base (question, answer) VALUES (?, ?)");
                $stmt->execute([$question, $answer]);
            }
            return true;
        } catch (PDOException $e) {
            // Tangkap dan lempar kembali exception untuk penanganan di level atas
            throw new Exception("Gagal menyimpan ke knowledge base: " . $e->getMessage());
        }
    }

    // Ambil semua percakapan terakhir
    public function getConversations($limit = 50) {
        $stmt = $this->pdo->prepare("SELECT * FROM conversations ORDER BY created_at DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Cari jawaban dari knowledge base berdasarkan pertanyaan mirip
    public function searchKnowledge($keyword) {
        $stmt = $this->pdo->prepare("SELECT * FROM knowledge_base WHERE question LIKE ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(["%$keyword%"]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Ambil feedback dengan skor rendah
    public function getLowScoreFeedback($threshold = 3.5) {
        $stmt = $this->pdo->prepare("
            SELECT f.*, c.user_message, c.ai_response 
            FROM feedback_scores f
            JOIN conversations c ON f.conversation_id = c.id
            WHERE f.score < ?
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Fungsi untuk menghindari duplikasi di knowledge_base
    public function cleanupKnowledgeBase() {
        // Hapus duplikasi, simpan yang paling baru
        $this->pdo->exec("
            DELETE FROM knowledge_base
            WHERE id NOT IN (
                SELECT MAX(id) FROM knowledge_base
                GROUP BY question
            )
        ");
        
        // Hapus entry kosong atau terlalu pendek
        $this->pdo->exec("
            DELETE FROM knowledge_base
            WHERE LENGTH(question) < 10 OR LENGTH(answer) < 10
        ");
    }
}
