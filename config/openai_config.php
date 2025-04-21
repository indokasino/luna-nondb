<?php
return [
    // API Key dari akun OpenAI kamu (pastikan ini aman dan tidak public)
    'api_key' => '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',

    // Model default, bisa diganti jadi gpt-4o / gpt-4 / gpt-3.5-turbo
    'model' => 'gpt-4.1',

    // Parameter tambahan
    'max_tokens' => 2000,
    'temperature' => 0.7,

    // Timeout curl
    'timeout' => 60
];
