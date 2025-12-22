<?php

// ==================== CONFIGURAÇÃO DE SESSÃO CORRIGIDA ====================

// 1. Configuração do Caminho (CRUCIAL PARA VERCEL)
// Define que o cookie vale para todo o domínio, corrigindo o problema da rota /api/
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 86400, // 24 horas
    'path'     => '/',   // Força o cookie a funcionar na raiz, não só em /api
    'domain'   => $_SERVER['HTTP_HOST'], // Opcional, mas ajuda
    'secure'   => true,  // Vercel é sempre HTTPS
    'httponly' => true,
    'samesite' => 'None' // Necessário para cookies cross-site/api
]);

// 2. Configuração do Local de Salvamento
// Nota: /tmp funciona, mas em serverless os dados podem sumir se a instância reiniciar.
// Para produção séria na Vercel, recomenda-se usar Redis ou Banco de Dados para sessões.
session_save_path('/tmp'); 

// 3. Início da Sessão
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
