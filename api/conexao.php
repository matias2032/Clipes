<?php
// A Vercel vai preencher getenv automaticamente com o que você salvou no painel
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');
$port = getenv('DB_PORT');

try {
    // Conectando usando a porta específica do Railway (44870)
    $conn = new mysqli($host, $user, $pass, $db, $port);
    
    if ($conn->connect_error) {
        throw new Exception("Falha: " . $conn->connect_error);
    }
    // Sucesso! O site agora está conectado ao banco na nuvem.
} catch (Exception $e) {
    echo "Erro técnico: O banco de dados está offline.";
}
?>
