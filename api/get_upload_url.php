<?php
/**
 * get_upload_url.php
 * Endpoint para gerar URL de upload direto do cliente para Vercel Blob
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar token
$token = getenv('BLOB_READ_WRITE_TOKEN');
if (!$token) {
    http_response_code(500);
    echo json_encode(['error' => 'BLOB_READ_WRITE_TOKEN não configurado']);
    exit;
}

// Receber dados do arquivo
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['filename']) || !isset($input['contentType'])) {
    http_response_code(400);
    echo json_encode(['error' => 'filename e contentType são obrigatórios']);
    exit;
}

$filename = $input['filename'];
$contentType = $input['contentType'];

// Gerar nome único
$extension = pathinfo($filename, PATHINFO_EXTENSION);
$uniqueFilename = uniqid('', true) . '_' . time() . '.' . $extension;

// URL do Vercel Blob para client upload
$uploadUrl = "https://blob.vercel-storage.com/" . urlencode($uniqueFilename);

// Retornar URL e token para o cliente fazer upload direto
echo json_encode([
    'uploadUrl' => $uploadUrl,
    'token' => $token,
    'filename' => $uniqueFilename,
    'publicUrl' => $uploadUrl // A URL pública será a mesma após upload bem-sucedido
]);
?>