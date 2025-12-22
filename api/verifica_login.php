<?php
// verifica_login.php - Para páginas PRIVADAS (login obrigatório)
// Este arquivo redireciona para login se não estiver autenticado
// Também pode verificar se é administrador

// ==================== CONFIGURAÇÃO DE SESSÃO ====================
// Configuração específica para Vercel
session_save_path('/tmp');

// Configurações de segurança
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Vercel usa HTTPS por padrão, isso é obrigatório
ini_set('session.cookie_samesite', 'None'); // Permite que o cookie transite entre as rotas da api

// Tempo de vida da sessão (24 horas)
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);

// Inicia a sessão se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    // Força o ID da sessão a ser persistente
if (isset($_COOKIE['PHPSESSID'])) {
    session_id($_COOKIE['PHPSESSID']);
}
    session_start();
}

// ==================== FUNÇÕES AUXILIARES ====================

// Verifica se usuário está logado
function isLoggedIn() {
    return isset($_SESSION['usuario']) && is_array($_SESSION['usuario']);
}

// Retorna dados do usuário logado (ou null)
function getUsuario() {
    return isLoggedIn() ? $_SESSION['usuario'] : null;
}

// Verifica se usuário tem perfil específico
function hasProfile($idperfil) {
    if (!isLoggedIn()) return false;
    return (int)$_SESSION['usuario']['idperfil'] === (int)$idperfil;
}

// Verifica se é administrador (idperfil = 1)
function isAdmin() {
    return hasProfile(1);
}

// Redireciona para login se não autenticado
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['url_destino'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit;
    }
}

// Redireciona para index se não for administrador
function requireAdmin() {
    requireLogin(); // Primeiro verifica se está logado
    if (!isAdmin()) {
        header("Location: index.php");
        exit;
    }
}

// ==================== LÓGICA DE VERIFICAÇÃO ====================

// Verifica se está logado (sempre redireciona se não estiver)
requireLogin();

// Captura dados do usuário (sempre disponível aqui)
$usuario = getUsuario();
?>