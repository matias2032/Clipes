<?php
// api/cloudinary_upload.php
// BIBLIOTECA PARA UPLOAD NO CLOUDINARY - VERSÃO VERCEL

function uploadToCloudinaryFromUrl($fileUrl, $folder = 'videos', $resourceType = 'video') {
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');
    
    if (!$cloudName || !$apiKey || !$apiSecret) {
        throw new Exception("Credenciais do Cloudinary não configuradas.");
    }
    
    $timestamp = time();
    $publicId = $folder . '/' . uniqid();
    
    // Gerar assinatura
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
    
    // Upload
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Erro no upload: " . $response);
    }
    
    $result = json_decode($response, true);
    return $result['secure_url'];
}

function uploadToCloudinaryBase64($base64Data, $folder = 'videos', $resourceType = 'video') {
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');
    
    if (!$cloudName || !$apiKey || !$apiSecret) {
        throw new Exception("Credenciais do Cloudinary não configuradas.");
    }
    
    $timestamp = time();
    $publicId = $folder . '/' . uniqid();
    
    // Gerar assinatura
    $paramsToSign = "folder={$folder}&public_id={$publicId}&timestamp={$timestamp}";
    $signature = sha1($paramsToSign . $apiSecret);
    
    // Preparar dados
    $postFields = [
        'file' => $base64Data,
        'api_key' => $apiKey,
        'timestamp' => $timestamp,
        'folder' => $folder,
        'public_id' => $publicId,
        'signature' => $signature
    ];
    
    // Upload
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Erro no upload: " . $response);
    }
    
    $result = json_decode($response, true);
    return $result['secure_url'];
}

function deleteFromCloudinary($publicId, $resourceType = 'video') {
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');
    
    $timestamp = time();
    $paramsToSign = "public_id={$publicId}&timestamp={$timestamp}";
    $signature = sha1($paramsToSign . $apiSecret);
    
    $postFields = [
        'api_key' => $apiKey,
        'timestamp' => $timestamp,
        'public_id' => $publicId,
        'signature' => $signature
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/destroy");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
?>