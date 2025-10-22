<?php
header('Content-Type: application/json');

function extractTextFromImage($imagePath) {
    $apiKey = 'K84486644788957'; // OCR.Space API key
    $url = 'https://api.ocr.space/parse/image';

    $imageData = curl_file_create($imagePath);
    $postFields = [
        'apikey' => $apiKey,
        'language' => 'eng',
        'isOverlayRequired' => false,
        'file' => $imageData
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['success' => false, 'error' => curl_error($ch)];
    }
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['IsErroredOnProcessing']) && $result['IsErroredOnProcessing'] == true) {
        return ['success' => false, 'error' => $result['ErrorMessage'][0] ?? 'Unknown OCR error'];
    }

    $parsedText = $result['ParsedResults'][0]['ParsedText'] ?? '';
    return ['success' => true, 'text' => $parsedText];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $tmpName = $_FILES['image']['tmp_name'];
    $result = extractTextFromImage($tmpName);
    echo json_encode($result);
    exit;
} else {
    echo json_encode(['success' => false, 'error' => 'No image uploaded']);
    exit;
} 