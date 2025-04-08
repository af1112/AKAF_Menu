<?php
header('Content-Type: application/json');

// Simple mock translation function (replace with actual API like Google Translate)
function mockTranslate($text, $source, $target) {
    // This is a mock function. In a real scenario, use Google Translate API or similar.
    $translations = [
        'en' => [
            'fa' => "ترجمه به فارسی: $text",
            'fr' => "Traduction en français: $text",
            'ar' => "الترجمة إلى العربية: $text"
        ],
        'fa' => [
            'en' => "Translation to English: $text",
            'fr' => "Traduction en français: $text",
            'ar' => "الترجمة إلى العربية: $text"
        ],
        'fr' => [
            'en' => "Translation to English: $text",
            'fa' => "ترجمه به فارسی: $text",
            'ar' => "الترجمة إلى العربية: $text"
        ],
        'ar' => [
            'en' => "Translation to English: $text",
            'fa' => "ترجمه به فارسی: $text",
            'fr' => "Traduction en français: $text"
        ]
    ];

    return $translations[$source][$target] ?? $text;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $text = $_POST['text'] ?? '';
    $source = $_POST['source'] ?? 'en';
    $target = $_POST['target'] ?? 'fa';

    $translatedText = mockTranslate($text, $source, $target);
    echo json_encode(['translatedText' => $translatedText]);
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>