<?php
// verifica_login.php - Para páginas PRIVADAS (login obrigatório)
// Este arquivo redireciona para login se não estiver autenticado
// VERSÃO OTIMIZADA PARA VERCEL COM SESSÕES NO MySQL

// ==================== INCLUIR DEPENDÊNCIAS ====================
require_once  "conexao.php"; // Sua conexão MySQL existente
require_once "sessao_handler_db.php"; // Handler customizado

// ==================== CONFIGURAR HANDLER CUSTOMIZADO ====================
$handler = new SessionHandlerDB($conexao);
session_set_save_handler($handler, true);

// ==================== CONFIGURAÇÃO DE COOKIES ====================
session_set_cookie_params([
    'lifetime' => 86400,    // 24 horas
    'path'     => '/',      // Cookie válido em todo o site
    'domain'   => '',       // Deixa PHP detectar automaticamente
    'secure'   => true,     // HTTPS obrigatório (Vercel sempre usa HTTPS)
    'httponly' => true,     // Proteção contra XSS
    'samesite' => 'Lax'     // Lax para navegação normal no mesmo site
]);

// ==================== NOME DA SESSÃO (opcional, mas recomendado) ====================
session_name('CLIPES_SESSION'); // Nome único para sua aplicação

// ==================== INICIAR SESSÃO ====================
if (session_status() === PHP_SESSION_NONE) {
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
