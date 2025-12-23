<?php
// logout.php - VERSÃO OTIMIZADA PARA MYSQL
require_once __DIR__ . "/conexao.php";
require_once __DIR__ . "/sessao_handler_db.php";

// Configurar handler
$handler = new SessionHandlerDB($conexao);
session_set_save_handler($handler, true);

// Configurar cookies
session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'domain'   => '',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_name('CLIPES_SESSION');

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Captura o perfil do usuário antes de destruir a sessão
$id_perfil = $_SESSION['usuario']['idperfil'] ?? null;

// ✅ Destrói completamente a sessão
session_unset();        // Limpa todas as variáveis
session_destroy();      // Destrói a sessão
session_write_close();  // Fecha e salva

// ✅ Remove o cookie do navegador
if (isset($_COOKIE['CLIPES_SESSION'])) {
    setcookie('CLIPES_SESSION', '', time() - 3600, '/', '', true, true);
}

// ✅ Redireciona sempre para index (independente do perfil)
header("Location: index.php");
exit;
?>
