<?php
/**
 * Script validasi koneksi API OpenAI
 * Gunakan untuk menguji apakah API key valid dan koneksi berfungsi
 * 
 * Cara penggunaan:
 * 1. Simpan file ini di direktori root aplikasi
 * 2. Jalankan melalui command line: php api_validator.php
 */

// Ambil konfigurasi
$openaiConfig = require __DIR__ . '/config/openai_config.php';
$config = require __DIR__ . '/config/config.php';

echo "======= VALIDATOR API OPENAI =======\n";
echo "Mulai validasi koneksi API OpenAI...\n";

// Tampilkan informasi konfigurasi (tanpa API key lengkap)
$apiKey = $openaiConfig['api_key'];
$apiKeyMasked = substr($apiKey, 0, 10) . '...' . substr($apiKey, -5);
$model = $openaiConfig['model'];

echo "Model: $model\n";
echo "API Key: $apiKeyMasked\n";
echo "Max Tokens: " . $openaiConfig['max_tokens'] . "\n";
echo "Timeout: " . $openaiConfig['timeout'] . " detik\n\n";

// Validasi format API key dasar
if (!preg_match('/^sk-/', $apiKey)) {
    echo "❌ ERROR: Format API key tidak valid. Seharusnya dimulai dengan 'sk-'\n";
    echo "Solusi: Periksa dan perbaiki API key di file config/openai_config.php\n";
    exit(1);
}

// Pesan uji sederhana
$testMessage = "Halo, ini adalah pesan test.";

// Baca prompt utama dari file
$promptPath = $config['prompt_path'];
$systemPrompt = file_exists($promptPath) ? file_get_contents($promptPath) : 'Anda adalah asisten AI.';

// Format payload untuk API OpenAI
$requestPayload = [
    'model' => $model,
    'messages' => [
        [
            'role' => 'system',
            'content' => $systemPrompt
        ],
        [
            'role' => 'user',
            'content' => $testMessage
        ]
    ],
    'max_tokens' => $openaiConfig['max_tokens'],
    'temperature' => $openaiConfig['temperature']
];

// Inisiasi curl request
echo "Mengirim permintaan ke API OpenAI...\n";
$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestPayload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_TIMEOUT => $openaiConfig['timeout']
]);

// Aktifkan verbose untuk melihat detail
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Eksekusi curl
$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Tampilkan hasil
echo "\nHTTP Response Code: $httpCode\n";

if ($error) {
    echo "❌ Curl Error: $error\n";
    echo "Solusi: Periksa koneksi internet atau firewall server\n";
    exit(1);
}

// Decode response JSON
$result = json_decode($response, true);

if ($httpCode === 401) {
    echo "❌ ERROR AUTENTIKASI (401): API key tidak valid atau kedaluwarsa\n";
    echo "Solusi: Verifikasi API key di OpenAI dashboard dan perbaiki di config/openai_config.php\n";
    echo "Pesan Error: " . ($result['error']['message'] ?? 'Unknown error') . "\n";
} elseif ($httpCode === 429) {
    echo "❌ ERROR RATE LIMIT (429): Terlalu banyak permintaan atau kuota habis\n";
    echo "Solusi: Tunggu beberapa menit atau periksa kredit dan billing di akun OpenAI\n";
    echo "Pesan Error: " . ($result['error']['message'] ?? 'Unknown error') . "\n";
} elseif ($httpCode !== 200) {
    echo "❌ ERROR LAINNYA ($httpCode): Terjadi kesalahan pada API\n";
    echo "Pesan Error: " . ($result['error']['message'] ?? 'Unknown error') . "\n";
} else {
    if (isset($result['choices'][0]['message']['content'])) {
        echo "✅ API BERFUNGSI DENGAN BAIK\n";
        echo "Preview Respons: " . substr($result['choices'][0]['message']['content'], 0, 100) . "...\n";
    } else {
        echo "❓ RESPONS VALID TAPI FORMAT TIDAK SESUAI\n";
        echo "Respons perlu diperiksa lebih lanjut.\n";
    }
}

echo "\n======= VALIDASI SELESAI =======\n";