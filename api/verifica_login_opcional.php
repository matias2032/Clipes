<?php
// verifica_login_opcional.php - Para páginas PÚBLICAS (login opcional)
// Este arquivo inicia sessão mas NÃO redireciona

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

// ==================== LÓGICA OPCIONAL ====================

// Captura usuário da sessão (pode ser null)
$usuario = getUsuario();

// Salva URL de origem para redirecionamento após login (opcional)
if (!isLoggedIn() && !isset($_SESSION['url_origem'])) {
    $_SESSION['url_origem'] = $_SERVER['REQUEST_URI'];
}
?>