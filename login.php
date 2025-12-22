<?php
require_once "verifica_login_opcional.php"; 
include "conexao.php"; 

$erro = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entrada = trim($_POST["entrada"]);
    $senha = $_POST["senha"];
    
    if (isset($_GET['redir'])) {
        $_SESSION['url_destino'] = basename($_GET['redir']);
    }

    // Buscar usuário
    $sql = "SELECT * FROM usuario WHERE nome= ?  LIMIT 1";
    $stmt = $conexao->prepare($sql);

    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conexao->error);
    }

    $stmt->bind_param("s", $entrada);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {
        $usuario = $resultado->fetch_assoc();

        // Verificar a senha
        if (password_verify($senha, $usuario['senha_hash'])) {
            // Regenerar ID da sessão para segurança
            session_regenerate_id(true);
            
            $_SESSION['usuario'] = $usuario;
            $_SESSION['id_usuario'] = $usuario['id_usuario']; // Salva ID explicitamente

            // Verifica senha padrão
            if ((int)$usuario['primeira_senha'] === 1) {
                header("Location: alterar_senha.php?primeiro=1");
                exit;
            }

            // Redirecionamento de URL salva
            if (isset($_SESSION['url_destino'])) {
                $urlDestino = $_SESSION['url_destino'];
                unset($_SESSION['url_destino']);
                header("Location: $urlDestino");
                exit;
            }

            // Redirecionamento por Perfil
            if ((int)$usuario['idperfil'] == 1) {
                header("Location: dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            $erro = "Incorrect password.";
            if (!empty($usuario['email'])) {
                $link_reset = "public/reset_password.php?email=" . urlencode($usuario['email']);
                $erro .= " <br><a href='$link_reset'>Forgot password?</a>";
            }
        }
    } else {
        $erro = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login</title>
    
    <link rel="stylesheet" href="../css/admin.css">
    
    <script src="../js/darkmode2.js"></script>
    <script src="../js/mostrarSenha.js"></script>
    <style>
        .logo {
            font-size: 1.5em;
            font-weight: bold;
            color: #d32f2f;
        }
        /* Estilo para mensagem de erro */
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-size: 0.9em;
            border: 1px solid #ef9a9a;
        }
        .alert-error a {
            color: #b71c1c;
            font-weight: bold;
        }
    </style>
</head>
<body>

<form method="POST" style="max-width: 400px; margin: 0 auto; text-align: center; margin-top:50px;" class="novo_user">

  <h3>Login</h3>
  
  <img src="../icones/logo.png" alt="Logo" style="display:block; margin: 10px auto; max-width:150px;">
  
  <?php if (!empty($erro)): ?>
      <div class="alert-error">
          <?= $erro ?>
      </div>
  <?php endif; ?>
  
  <div style="text-align: left; margin-top: 10px;">
    <label>User:</label>
    <input type="text" name="entrada" placeholder="name, email or number" required value="<?= htmlspecialchars($_POST['entrada'] ?? '') ?>"><br><br>

    <label for="senha" style="display: block; text-align: left; margin-top: 10px;">Password:</label>
    <div style="position: relative; display: flex; align-items: center; justify-content: center;">
      <input type="password" name="senha" class="campo-senha" required
             style="width: 100%; padding-right: 35px; box-sizing: border-box;">
      
      <img src="../icones/olho_fechado1.png"
           alt="Show password"
           class="toggle-senha"
           data-target="campo-senha"
           style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
    </div>
  </div>

  <button type="submit" style="margin-top: 10px;">Login</button>

  <p style="margin-top: 10px;">
    Don't have an account? <a href="cadastro.php">Click here</a>
  </p>

</form>

</body>
</html>