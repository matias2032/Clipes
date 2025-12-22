<?php
// verifica_login.php
session_save_path('/tmp'); // Configuração para Vercel ANTES do start
session_start();

if (!isset($_SESSION['usuario'])) {
    $urlAtual = $_SERVER['REQUEST_URI'];
    $_SESSION['url_destino'] = $urlAtual;

    header("Location: login.php");
    exit;
}
$usuario = $_SESSION['usuario'];
?>