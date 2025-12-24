<?php
/**
 * BIBLIOTECA PARA UPLOAD NO CLOUDINARY - VERSÃO VERCEL
 * 
 * Este arquivo contém funções auxiliares para upload e gestão de mídia no Cloudinary.
 * NÃO é um endpoint HTTP direto, portanto NÃO deve ter headers CORS.
 * É incluído por outros arquivos PHP que precisam fazer upload de arquivos.
 */

/**
 * Faz upload de arquivo para Cloudinary a partir de uma URL
 * 
 * @param string $fileUrl URL do arquivo a ser carregado
 * @param string $folder Pasta de destino no Cloudinary
 * @param string $resourceType Tipo de recurso (video, image, raw)
 * @return string URL segura do arquivo carregado
 * @throws Exception Se houver erro no upload
 */
function uploadToCloudinaryFromUrl($fileUrl, $folder = 'videos', $resourceType = 'video') {
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');
    
    if (!$cloudName || !$apiKey || !$apiSecret) {
        throw new Exception("Credenciais do Cloudinary não configuradas. Verifique as variáveis de ambiente no painel da Vercel.");
    }
    
    $timestamp = time();
    $publicId = $folder . '/' . uniqid();
    
    // Gerar assinatura para autenticação
    $paramsToSign = "folder={$folder}&public_id={$publicId}&timestamp={$timestamp}";
    $signature = sha1($paramsToSign . $apiSecret);
    
    // Preparar dados para upload via URL
    $postFields = [
        'file' => $fileUrl,
        'api_key' => $apiKey,
        'timestamp' => $timestamp,
        'folder' => $folder,
        'public_id' => $publicId,
        'signature' => $signature
    ];
    
    // Fazer upload via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Timeout de 5 minutos para uploads grandes
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $errorMsg = "Erro no upload para Cloudinary (HTTP {$httpCode})";
        if ($curlError) {
            $errorMsg .= ": {$curlError}";
        }
        if ($response) {
            $errorMsg .= " - Resposta: {$response}";
        }
        throw new Exception($errorMsg);
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['secure_url'])) {
        throw new Exception("Upload concluído mas URL segura não foi retornada: " . $response);
    }
    
    return $result['secure_url'];
}

/**
 * Faz upload de arquivo para Cloudinary a partir de dados Base64
 * 
 * @param string $base64Data Dados do arquivo em formato Base64 (data:image/png;base64,...)
 * @param string $folder Pasta de destino no Cloudinary
 * @param string $resourceType Tipo de recurso (video, image, raw)
 * @return string URL segura do arquivo carregado
 * @throws Exception Se houver erro no upload
 */
function uploadToCloudinaryBase64($base64Data, $folder = 'videos', $resourceType = 'video') {
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');
    
    if (!$cloudName || !$apiKey || !$apiSecret) {
        throw new Exception("Credenciais do Cloudinary não configuradas. Verifique as variáveis de ambiente no painel da Vercel.");
    }
    
    // Validar dados Base64
    if (empty($base64Data) || !preg_match('/^data:([a-zA-Z0-9]+\/[a-zA-Z0-9\-\+\.]+);base64,/', $base64Data)) {
        throw new Exception("Dados Base64 inválidos ou ausentes.");
    }
    
    $timestamp = time();
    $publicId = $folder . '/' . uniqid();
    
    // Gerar assinatura para autenticação
    $paramsToSign = "folder={$folder}&public_id={$publicId}&timestamp={$timestamp}";
    $signature = sha1($paramsToSign . $apiSecret);
    
    // Preparar dados para upload
    $postFields = [
        'file' => $base64Data,
        'api_key' => $apiKey,
        'timestamp' => $timestamp,
        'folder' => $folder,
        'public_id' => $publicId,
        'signature' => $signature
    ];
    
    // Fazer upload via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Timeout de 5 minutos para uploads grandes
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $errorMsg = "Erro no upload para Cloudinary (HTTP {$httpCode})";
        if ($curlError) {
            $errorMsg .= ": {$curlError}";
        }
        if ($response) {
            $errorMsg .= " - Resposta: {$response}";
        }
        throw new Exception($errorMsg);
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['secure_url'])) {
        throw new Exception("Upload concluído mas URL segura não foi retornada: " . $response);
    }
    
    return $result['secure_url'];
}

/**
 * Remove arquivo do Cloudinary
 * 
 * @param string $publicId ID público do arquivo no Cloudinary (ex: videos/previas/abc123)
 * @param string $resourceType Tipo de recurso (video, image, raw)
 * @return array Resposta da API do Cloudinary
 * @throws Exception Se houver erro na remoção
 */
function deleteFromCloudinary($publicId, $resourceType = 'video') {
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');
    
    if (!$cloudName || !$apiKey || !$apiSecret) {
        throw new Exception("Credenciais do Cloudinary não configuradas.");
    }
    
    $timestamp = time();
    
    // Gerar assinatura para autenticação
    $paramsToSign = "public_id={$publicId}&timestamp={$timestamp}";
    $signature = sha1($paramsToSign . $apiSecret);
    
    // Preparar dados para remoção
    $postFields = [
        'api_key' => $apiKey,
        'timestamp' => $timestamp,
        'public_id' => $publicId,
        'signature' => $signature
    ];
    
    // Fazer requisição de remoção via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/destroy");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception("Erro ao remover arquivo: {$curlError}");
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode !== 200 && $httpCode !== 404) {
        throw new Exception("Erro ao remover arquivo (HTTP {$httpCode}): " . $response);
    }
    
    return $result;
}

/**
 * Extrai o public_id de uma URL do Cloudinary
 * 
 * @param string $cloudinaryUrl URL completa do Cloudinary
 * @return string|null Public ID extraído ou null se não for possível extrair
 */
function extractPublicIdFromUrl($cloudinaryUrl) {
    // Exemplo de URL: https://res.cloudinary.com/cloud_name/video/upload/v1234567890/videos/previas/abc123.mp4
    // Public ID: videos/previas/abc123
    
    $pattern = '/\/upload\/(?:v\d+\/)?(.+)\.\w+$/';
    
    if (preg_match($pattern, $cloudinaryUrl, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Verifica se as credenciais do Cloudinary estão configuradas
 * 
 * @return bool True se todas as credenciais estão presentes
 */
function isCloudinaryConfigured() {
    return !empty(getenv('CLOUDINARY_CLOUD_NAME')) && 
           !empty(getenv('CLOUDINARY_API_KEY')) && 
           !empty(getenv('CLOUDINARY_API_SECRET'));
}

/**
 * Obtém informações sobre um arquivo no Cloudinary
 * 
 * @param string $publicId ID público do arquivo
 * @param string $resourceType Tipo de recurso
 * @return array|null Informações do arquivo ou null se não encontrado
 */
function getCloudinaryAssetInfo($publicId, $resourceType = 'video') {
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');
    
    if (!$cloudName || !$apiKey || !$apiSecret) {
        return null;
    }
    
    $timestamp = time();
    $signature = sha1("public_id={$publicId}&timestamp={$timestamp}" . $apiSecret);
    
    $url = "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload/{$publicId}";
    $url .= "?api_key={$apiKey}&timestamp={$timestamp}&signature={$signature}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}
?>