<?php
/**
 * Webhook dengan toleransi error lebih baik
 * - Penanganan API error yang lebih robust
 * - Menyimpan percakapan meskipun ada error API
 * - Logging yang lebih detail
 * - Penanganan database read-only
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/response_formatter.php';
$config = require __DIR__ . '/../config/config.php';
$openaiConfig = require __DIR__ . '/../config/openai_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Fungsi logging yang lebih baik
function logMessage($message, $type = 'INFO') {
    global $config;
    if ($config['debug_mode'] || $type == 'ERROR') {
        $logFile = $config['log_file'];
        $logDir = dirname($logFile);
        
        // Pastikan direktori log ada
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d\TH:i:s');
        file_put_contents($logFile, "$timestamp | [$type] $message\n", FILE_APPEND);
    }
}

// Fungsi untuk generate AI response dengan error handling lebih baik
function generateAIResponse($userMessage) {
    global $openaiConfig, $config;
    
    $apiKey = $openaiConfig['api_key'];
    $model = $openaiConfig['model'];
    $temperature = $openaiConfig['temperature'];
    $maxTokens = $openaiConfig['max_tokens'];
    $timeout = $openaiConfig['timeout'];

    // Validasi API key
    if (empty($apiKey) || !preg_match('/^sk-/', $apiKey)) {
        logMessage("API key tidak valid: $apiKey", 'ERROR');
        throw new Exception("API key tidak valid. Harap periksa konfigurasi.");
    }

    // Baca prompt utama dari file
    $promptPath = $config['prompt_path'];
    if (!file_exists($promptPath)) {
        logMessage("File prompt tidak ditemukan: $promptPath", 'ERROR');
        throw new Exception("File prompt tidak ditemukan di: $promptPath");
    }
    
    $systemPrompt = file_get_contents($promptPath);
    
    $requestPayload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => $userMessage
            ]
        ],
        'max_tokens' => $maxTokens,
        'temperature' => $temperature
    ];

    logMessage("Mengirim request ke OpenAI - User: " . substr($userMessage, 0, 50) . "...");
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestPayload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => $timeout
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log hasil response secara detail
    logMessage("OpenAI Response - HTTP Code: $httpCode");
    
    if ($error) {
        logMessage("Curl Error: $error", 'ERROR');
        throw new Exception("Koneksi ke OpenAI gagal: $error");
    }

    $result = json_decode($response, true);
    
    // Penanganan error HTTP
    if ($httpCode !== 200) {
        $errorMsg = isset($result['error']['message']) ? $result['error']['message'] : "Unknown API error";
        logMessage("OpenAI Error [$httpCode]: $errorMsg", 'ERROR');
        
        // Penanganan error spesifik
        if ($httpCode === 401) {
            throw new Exception("API key tidak valid atau kedaluwarsa. Harap perbarui API key.");
        } elseif ($httpCode === 429) {
            throw new Exception("Rate limit terlampaui atau kredit habis. Harap periksa akun OpenAI Anda.");
        } else {
            throw new Exception("Error API OpenAI: $errorMsg");
        }
    }

    // Validasi format response
    if (!isset($result['choices'][0]['message']['content'])) {
        logMessage("Format respon API tidak sesuai: " . json_encode($result), 'ERROR');
        throw new Exception("Format respons API tidak sesuai yang diharapkan.");
    }

    $aiResponse = trim($result['choices'][0]['message']['content']);
    logMessage("Respons AI berhasil dibuat - " . substr($aiResponse, 0, 50) . "...");
    
    return $aiResponse;
}

// Handle GET Challenge (penting untuk verifikasi awal webhook)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['challenge'])) {
    if (isset($_GET['token']) && $_GET['token'] !== $config['token_verification']) {
        logMessage("Token verifikasi tidak valid: " . $_GET['token'], 'ERROR');
        http_response_code(401);
        echo 'Unauthorized';
        exit;
    }
    echo $_GET['challenge'];
    exit;
}

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit(0);
}

// Cek jika bukan POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logMessage("Method tidak diizinkan: " . $_SERVER['REQUEST_METHOD'], 'ERROR');
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Ambil input dan decode JSON
$rawInput = file_get_contents('php://input');
logMessage("Raw input: " . $rawInput);

$input = json_decode($rawInput, true);
if (!$input) {
    logMessage("Input tidak valid JSON: " . $rawInput, 'ERROR');
    echo json_encode(formatChatbotResponse("Data tidak valid, bosku."));
    exit;
}

// Ambil pesan user dari struktur
$userMessage = '';
if (isset($input['responses']) && is_array($input['responses'])) {
    foreach ($input['responses'] as $res) {
        if (isset($res['type']) && $res['type'] === 'INPUT_MESSAGE' && isset($res['value'])) {
            $userMessage = trim($res['value']);
            break;
        }
    }
} elseif (isset($input['message'])) {
    $userMessage = trim($input['message']);
}

if (empty($userMessage)) {
    logMessage("Pesan user kosong dari input", 'ERROR');
    echo json_encode(formatChatbotResponse("Maaf, saya tidak menerima pertanyaannya. Silakan ulangi."));
    exit;
}

try {
    // Mendapatkan respons AI
    $aiResponse = "Maaf bosku, ada gangguan teknis. Coba lagi nanti atau ketik 'CS' untuk dibantu oleh tim support.";
    $isAIGenerated = false;
    
    try {
        // Coba dapatkan respon dari API
        $aiResponse = generateAIResponse($userMessage);
        $isAIGenerated = true;
        logMessage("Berhasil generate AI response untuk: " . substr($userMessage, 0, 30));
    } catch (Exception $apiError) {
        logMessage("Gagal generate AI response: " . $apiError->getMessage(), 'ERROR');
        // Jika gagal, gunakan respon default yang sudah diset
    }
    
    // Coba simpan percakapan ke database
    try {
        // Inisialisasi database
        $db = new LunaDB($config['db_path']);
        
        // Simpan ke DB conversations
        $conversationId = $db->saveConversation($userMessage, $aiResponse);
        logMessage("Berhasil menyimpan percakapan dengan ID: $conversationId");
        
        // Simpan juga ke knowledge_base jika kualitas memadai
        if ($isAIGenerated) {
            $wordCount = str_word_count($userMessage);
            if ($wordCount >= 3 && strlen($aiResponse) > 20) {
                try {
                    $db->addKnowledge($userMessage, $aiResponse);
                    logMessage("Berhasil menyimpan ke knowledge_base: " . substr($userMessage, 0, 30));
                } catch (Exception $kbError) {
                    logMessage("Gagal menyimpan ke knowledge_base: " . $kbError->getMessage(), 'ERROR');
                    // Tetap lanjut meskipun gagal menyimpan ke knowledge_base
                }
            }
        }
    } catch (Exception $dbError) {
        logMessage("Error database: " . $dbError->getMessage(), 'ERROR');
        // Jika error terkait readonly database, coba lewati dan tetap berikan respon
        if (strpos($dbError->getMessage(), 'readonly database') !== false) {
            logMessage("Database readonly, tetap melanjutkan dengan respon AI", 'ERROR');
        }
    }
    
    // Selalu kembalikan respons AI, tidak peduli apakah penyimpanan berhasil atau tidak
    echo json_encode(formatChatbotResponse($aiResponse));
    
} catch (Exception $e) {
    // Catch-all untuk error tak terduga
    logMessage("Error tak terduga: " . $e->getMessage(), 'ERROR');
    echo json_encode(formatChatbotResponse(
        "Maaf bosku, ada gangguan teknis. Coba lagi nanti atau ketik 'CS' untuk dibantu oleh tim support."
    ));
}
