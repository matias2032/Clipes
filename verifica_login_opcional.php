<?php
// verifica_login_opcional.php
session_save_path('/tmp'); // Configuração para Vercel ANTES do start

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario = $_SESSION['usuario'] ?? null;

if (!isset($_SESSION['usuario'])) {
    $_SESSION['url_origem'] = $_SERVER['REQUEST_URI'];
}
?>