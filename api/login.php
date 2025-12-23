<?php
// login.php - VERSÃO OTIMIZADA PARA MYSQL
require_once  "conexao.php";
require_once  "sessao_handler_db.php";

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

$erro = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entrada = trim($_POST["entrada"]);
    $senha = $_POST["senha"];
    
    // Salva URL de redirecionamento
    if (isset($_GET['redir'])) {
        $_SESSION['url_destino'] = basename($_GET['redir']);
    }

    $sql = "SELECT * FROM usuario WHERE nome = ? LIMIT 1";
    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conexao->error);
    }

    $stmt->bind_param("s", $entrada);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $usuario = $resultado->fetch_assoc();

        if (password_verify($senha, $usuario['senha_hash'])) {
            
            // CRÍTICO: Regenerar ID ANTES de salvar dados
            session_regenerate_id(true);
            
            // Salvar dados do usuário
            $_SESSION['usuario'] = [
                'id_usuario' => $usuario['id_usuario'],
                'nome' => $usuario['nome'],
                'apelido' => $usuario['apelido'],
                'idperfil' => $usuario['idperfil'],
                'primeira_senha' => $usuario['primeira_senha']
            ];

            // ✅ FORÇA GRAVAÇÃO DA SESSÃO (ESSENCIAL NO SERVERLESS)
            session_write_close();
            
            // ✅ REINICIA SESSÃO para próxima requisição
            session_start();

            // Debug (remova após testar)
            error_log("LOGIN SUCESSO - ID Sessão: " . session_id());
            error_log("LOGIN SUCESSO - Dados: " . print_r($_SESSION['usuario'], true));

            // Primeira senha
            if ((int)$usuario['primeira_senha'] === 1) {
                header("Location: alterar_senha.php?primeiro=1");
                exit;
            }

            // Redirecionamento por URL salva
            if (isset($_SESSION['url_destino'])) {
                $urlDestino = $_SESSION['url_destino'];
                unset($_SESSION['url_destino']);
                header("Location: " . $urlDestino);
                exit;
            }

            // Redirecionamento por perfil
            if ((int)$usuario['idperfil'] === 1) {
                header("Location: dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit;
            
        } else {
            $erro = "Senha incorreta.";
        }
    } else {
        $erro = "Usuário não encontrado.";
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../css/admin.css">
    <script src="../js/darkmode2.js"></script>
    <script src="../js/mostrarSenha.js"></script>
    <style>
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #ef9a9a;
        }
    </style>
</head>
<body>

<form method="POST" style="max-width: 400px; margin: 50px auto; text-align: center;" class="novo_user">
  <h3>Login</h3>
  
  <img src="../icones/logo.png" alt="Logo" style="display:block; margin: 10px auto; max-width:150px;">
  
  <?php if (!empty($erro)): ?>
      <div class="alert-error">
          <?= htmlspecialchars($erro) ?>
      </div>
  <?php endif; ?>
  
  <div style="text-align: left; margin-top: 10px;">
    <label>Usuário:</label>
    <input type="text" name="entrada" placeholder="nome" required 
           value="<?= htmlspecialchars($_POST['entrada'] ?? '') ?>"><br><br>

    <label for="senha">Senha:</label>
    <div style="position: relative; display: flex; align-items: center;">
      <input type="password" name="senha" class="campo-senha" required
             style="width: 100%; padding-right: 35px;">
      
      <img src="../icones/olho_fechado1.png"
           alt="Mostrar senha"
           class="toggle-senha"
           data-target="campo-senha"
           style="position: absolute; right: 10px; cursor: pointer; width: 22px;">
    </div>
  </div>

  <button type="submit" style="margin-top: 20px;">Entrar</button>

  <p style="margin-top: 10px;">
    Não tem conta? <a href="cadastro.php">Cadastre-se aqui</a>
  </p>
</form>

</body>
</html>
