<?php
// verifica_login_opcional.php - Para páginas PÚBLICAS (login opcional)

// ==================== INCLUIR DEPENDÊNCIAS ====================
require "conexao.php"; // Sua conexão MySQL existente
require "sessao_handler_db.php"; // Handler customizado

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

// ==================== LÓGICA OPCIONAL ====================

// Captura usuário da sessão (pode ser null)
$usuario = getUsuario();

// Salva URL de origem para redirecionamento após login (opcional)
if (!isLoggedIn() && !isset($_SESSION['url_origem'])) {
    $_SESSION['url_origem'] = $_SERVER['REQUEST_URI'];
}
?>
