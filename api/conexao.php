<?php
// A Vercel vai preencher getenv automaticamente com o que você salvou no painel
$host = getenv('DB_HOST');
$usuario = getenv('DB_USER');
$password = getenv('DB_PASS');
$basededados   = getenv('DB_NAME');
$port = getenv('DB_PORT');

try {
    // Conectando usando a porta específica do Railway (44870)
    $conexao = new mysqli($host, $usuario, $password, $basededados, $port);
    
    if ($conexao->connect_error) {
        throw new Exception("Falha: " . $conexao->connect_error);
    }
    // Sucesso! O site agora está conectado ao banco na nuvem.
} catch (Exception $e) {
    echo "Erro técnico: O banco de dados está offline.";
}
?>
