<?php
return [
    // Token verifikasi untuk validasi dari Chatbot.com
    'token_verification' => '67f0deebabf4820078b6dcc',

    // Lokasi file prompt karakter Luna
    'prompt_path' => __DIR__ . '/../prompt/prompt-luna.txt',

    // Lokasi file log webhook
    'log_file' => __DIR__ . '/../logs/webhook_log.txt',

    // Lokasi database SQLite
    'db_path' => __DIR__ . '/../database/luna.sqlite',

    // Skor minimal agar jawaban dianggap "relevan"
    'feedback_threshold' => 3.5, // dari 0-5, bisa kamu sesuaikan

    // Mode pengembangan: true = log lebih detail
    'debug_mode' => true
];
