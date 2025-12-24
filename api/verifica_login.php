<?php
/**
 * verifica_login.php - Para páginas PRIVADAS (login obrigatório)
 * VERSÃO OTIMIZADA PARA VERCEL - COM OUTPUT BUFFERING INTEGRADO
 */

// ==================== INICIAR OUTPUT BUFFERING IMEDIATAMENTE ====================
// CRÍTICO: Deve ser a PRIMEIRA linha para capturar qualquer saída prematura
if (ob_get_level() === 0) {
    ob_start();
}

// ==================== INCLUIR DEPENDÊNCIAS ====================
require_once "conexao.php";
require_once "sessao_handler_db.php";

// ==================== CONFIGURAR HANDLER CUSTOMIZADO ====================
$handler = new SessionHandlerDB($conexao);
session_set_save_handler($handler, true);

// ==================== CONFIGURAÇÃO DE COOKIES ====================
session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'domain'   => '',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// ==================== NOME DA SESSÃO ====================
session_name('CLIPES_SESSION');

// ==================== INICIAR SESSÃO ====================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== FUNÇÕES AUXILIARES ====================

function isLoggedIn() {
    return isset($_SESSION['usuario']) && is_array($_SESSION['usuario']);
}

function getUsuario() {
    return isLoggedIn() ? $_SESSION['usuario'] : null;
}

function hasProfile($idperfil) {
    if (!isLoggedIn()) return false;
    return (int)$_SESSION['usuario']['idperfil'] === (int)$idperfil;
}

function isAdmin() {
    return hasProfile(1);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Limpar buffer antes de redirecionar
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $_SESSION['url_destino'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        // Limpar buffer antes de redirecionar
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Location: index.php");
        exit;
    }
}

// ==================== LÓGICA DE VERIFICAÇÃO ====================
requireLogin();
$usuario = getUsuario();
?>