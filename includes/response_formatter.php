<?php

function formatChatbotResponse($message) {
    return [
        'responses' => [
            [
                'type' => 'text',
                'message' => $message
            ]
        ]
    ];
}
