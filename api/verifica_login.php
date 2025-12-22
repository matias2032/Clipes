<?php
// verifica_login.php - Para páginas PRIVADAS (login obrigatório)
// Este arquivo redireciona para login se não estiver autenticado
// Também pode verificar se é administrador

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
