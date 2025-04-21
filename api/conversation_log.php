<?php

require_once __DIR__ . '/../includes/database.php';
$config = require __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Cek method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $db = new LunaDB($config['db_path']);
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $conversations = $db->getConversations($limit);

    echo json_encode([
        'status' => 'ok',
        'data' => $conversations
    ]);
} catch (Exception $e) {
    if ($config['debug_mode']) {
        file_put_contents($config['log_file'], date('c') . " [ERROR] LogAPI: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mengambil log percakapan.'
    ]);
}
