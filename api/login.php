<?php
// login.php - VERSÃO CONSOLIDADA
require_once "verifica_login_opcional.php"; // Apenas inicia sessão
require_once "conexao.php";

$erro = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entrada = trim($_POST["entrada"]);
    $senha = $_POST["senha"];
    
    // Salva URL de redirecionamento se existir
    if (isset($_GET['redir'])) {
        $_SESSION['url_destino'] = basename($_GET['redir']);
    }

    // Buscar usuário por nome
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

        // Verificar a senha
        if (password_verify($senha, $usuario['senha_hash'])) {
            
            // CRITICAL: Regenerar ID da sessão ANTES de salvar dados
            session_regenerate_id(true);
            
            // Salvar dados do usuário na sessão
            $_SESSION['usuario'] = [
                'id_usuario' => $usuario['id_usuario'],
                'nome' => $usuario['nome'],
                'apelido' => $usuario['apelido'],
                'idperfil' => $usuario['idperfil'],
                'primeira_senha' => $usuario['primeira_senha']
            ];

            // Verifica se é primeira senha
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

            // Redirecionamento por Perfil usando função auxiliar
            if (isAdmin()) {
                header("Location: dashboard.php");
                exit;
            } else {
                header("Location: index.php");
                exit;
            }
            
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
        .logo {
            font-size: 1.5em;
            font-weight: bold;
            color: #d32f2f;
        }
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
    <input type="text" name="entrada" placeholder="nome, email ou número" required 
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