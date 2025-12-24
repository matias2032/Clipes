<?php

//vercel_blob_upload.php
/**
 * BIBLIOTECA PARA UPLOAD NO VERCEL BLOB
 * 
 * Este arquivo contém funções auxiliares para upload e gestão de mídia no Vercel Blob.
 * NÃO é um endpoint HTTP direto, portanto NÃO deve ter headers CORS.
 * É incluído por outros arquivos PHP que precisam fazer upload de arquivos.
 * 
 * Documentação: https://vercel.com/docs/storage/vercel-blob
 */

/**
 * Verifica se o Vercel Blob está configurado
 * 
 * @return bool True se o token está presente
 */
function isVercelBlobConfigured() {
    return !empty(getenv('BLOB_READ_WRITE_TOKEN'));
}

/**
 * Faz upload de arquivo para Vercel Blob a partir de dados Base64
 * 
 * @param string $base64Data Dados do arquivo em formato Base64 (data:image/png;base64,...)
 * @param string $filename Nome do arquivo (ex: video_123.mp4)
 * @param string $contentType MIME type do arquivo (ex: video/mp4, image/jpeg)
 * @return string URL pública do arquivo carregado
 * @throws Exception Se houver erro no upload
 */
function uploadToVercelBlobBase64($base64Data, $filename, $contentType = 'application/octet-stream') {
    $token = getenv('BLOB_READ_WRITE_TOKEN');
    
    if (!$token) {
        throw new Exception("Token do Vercel Blob não configurado. Adicione BLOB_READ_WRITE_TOKEN nas variáveis de ambiente.");
    }
    
    // Validar dados Base64
    if (empty($base64Data) || !preg_match('/^data:([a-zA-Z0-9]+\/[a-zA-Z0-9\-\+\.]+);base64,/', $base64Data, $matches)) {
        throw new Exception("Dados Base64 inválidos ou ausentes.");
    }
    
    // Extrair MIME type do Base64 se não foi fornecido explicitamente
    if ($contentType === 'application/octet-stream' && isset($matches[1])) {
        $contentType = $matches[1];
    }
    
    // Remover prefixo Base64 e decodificar
    $base64Content = preg_replace('/^data:[^;]+;base64,/', '', $base64Data);
    $binaryData = base64_decode($base64Content);
    
    if ($binaryData === false) {
        throw new Exception("Falha ao decodificar dados Base64.");
    }
    
    // Gerar nome único se necessário
    if (empty($filename)) {
        $extension = explode('/', $contentType)[1] ?? 'bin';
        $filename = uniqid('upload_', true) . '.' . $extension;
    }
    
    // Preparar requisição para Vercel Blob
    $url = "https://blob.vercel-storage.com/{$filename}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $binaryData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutos para uploads grandes
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        "Content-Type: {$contentType}",
        "x-content-type: {$contentType}"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log para debug
    error_log("Vercel Blob Upload - HTTP {$httpCode}: {$response}");
    
    if ($curlError) {
        throw new Exception("Erro de conexão ao Vercel Blob: {$curlError}");
    }
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        throw new Exception("Erro no upload para Vercel Blob (HTTP {$httpCode}): {$response}");
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['url'])) {
        throw new Exception("Upload concluído mas URL não foi retornada: " . $response);
    }
    
    return $result['url'];
}

/**
 * Faz upload de arquivo para Vercel Blob a partir de um arquivo temporário
 * 
 * @param string $tmpPath Caminho do arquivo temporário
 * @param string $filename Nome do arquivo de destino
 * @param string $contentType MIME type do arquivo
 * @return string URL pública do arquivo carregado
 * @throws Exception Se houver erro no upload
 */
function uploadToVercelBlobFromFile($tmpPath, $filename, $contentType = 'application/octet-stream') {
    if (!file_exists($tmpPath)) {
        throw new Exception("Arquivo temporário não encontrado: {$tmpPath}");
    }
    
    // Ler arquivo e converter para Base64
    $binaryData = file_get_contents($tmpPath);
    if ($binaryData === false) {
        throw new Exception("Falha ao ler arquivo temporário.");
    }
    
    $base64Data = 'data:' . $contentType . ';base64,' . base64_encode($binaryData);
    
    return uploadToVercelBlobBase64($base64Data, $filename, $contentType);
}

/**
 * Remove arquivo do Vercel Blob
 * 
 * @param string $url URL completa do arquivo no Vercel Blob
 * @return bool True se removido com sucesso
 * @throws Exception Se houver erro na remoção
 */
function deleteFromVercelBlob($url) {
    $token = getenv('BLOB_READ_WRITE_TOKEN');
    
    if (!$token) {
        throw new Exception("Token do Vercel Blob não configurado.");
    }
    
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception("URL inválida para remoção: {$url}");
    }
    
    // Vercel Blob usa DELETE na mesma URL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception("Erro ao remover arquivo: {$curlError}");
    }
    
    // 200, 204 ou 404 são considerados sucesso
    if ($httpCode !== 200 && $httpCode !== 204 && $httpCode !== 404) {
        throw new Exception("Erro ao remover arquivo (HTTP {$httpCode}): " . $response);
    }
    
    return true;
}

/**
 * Gera nome de arquivo único e seguro
 * 
 * @param string $originalName Nome original do arquivo
 * @param string $prefix Prefixo para organização (ex: 'videos', 'images')
 * @return string Nome de arquivo único
 */
function generateSafeFilename($originalName, $prefix = '') {
    // Extrair extensão
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // Gerar nome único
    $uniqueId = uniqid('', true);
    $timestamp = time();
    
    // Montar nome seguro
    $safeName = $prefix ? "{$prefix}_{$timestamp}_{$uniqueId}" : "{$timestamp}_{$uniqueId}";
    
    if ($extension) {
        $safeName .= ".{$extension}";
    }
    
    return $safeName;
}

/**
 * Detecta MIME type de dados Base64
 * 
 * @param string $base64Data Dados em Base64
 * @return string MIME type detectado
 */
function detectMimeTypeFromBase64($base64Data) {
    if (preg_match('/^data:([a-zA-Z0-9]+\/[a-zA-Z0-9\-\+\.]+);base64,/', $base64Data, $matches)) {
        return $matches[1];
    }
    
    return 'application/octet-stream';
}

/**
 * Valida se o arquivo é um vídeo permitido
 * 
 * @param string $mimeType MIME type do arquivo
 * @return bool True se for vídeo válido
 */
function isValidVideo($mimeType) {
    $allowedTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
    return in_array(strtolower($mimeType), $allowedTypes);
}

/**
 * Valida se o arquivo é uma imagem permitida
 * 
 * @param string $mimeType MIME type do arquivo
 * @return bool True se for imagem válida
 */
function isValidImage($mimeType) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    return in_array(strtolower($mimeType), $allowedTypes);
}

/**
 * Obtém tamanho do arquivo em bytes a partir de Base64
 * 
 * @param string $base64Data Dados em Base64
 * @return int Tamanho em bytes
 */
function getBase64FileSize($base64Data) {
    $base64Content = preg_replace('/^data:[^;]+;base64,/', '', $base64Data);
    $decodedSize = strlen(base64_decode($base64Content));
    return $decodedSize;
}

/**
 * Formata tamanho de arquivo para exibição
 * 
 * @param int $bytes Tamanho em bytes
 * @return string Tamanho formatado (ex: "2.5 MB")
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>