<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/openai_config.php';

function generateAIResponse($userMessage) {
    global $config;
    $openaiConfig = require __DIR__ . '/../config/openai_config.php';

    $apiKey = $openaiConfig['api_key'];
    $model = $openaiConfig['model'];
    $temperature = $openaiConfig['temperature'];
    $maxTokens = $openaiConfig['max_tokens'];
    $timeout = $openaiConfig['timeout'];

    // Baca prompt utama dari file
    $promptPath = $config['prompt_path'];
    $systemPrompt = file_exists($promptPath) ? file_get_contents($promptPath) : 'Anda adalah asisten AI.';

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

    // Logging jika mode debug aktif
    if ($config['debug_mode']) {
        file_put_contents($config['log_file'], date('c') . " [GPT] HTTP $httpCode - " . substr($response, 0, 500) . "\n", FILE_APPEND);
    }

    if ($error) {
        throw new Exception("Curl Error: $error");
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($result['choices'][0]['message']['content'])) {
        throw new Exception("API Response Error: Format tidak dikenali");
    }

    return trim($result['choices'][0]['message']['content']);
}
