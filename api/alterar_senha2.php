<?php
require_once "verifica_login.php";
include "conexao.php";
include "info_usuario.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}


$usuario_logado = $_SESSION['usuario'];
$id_usuario = $usuario_logado['id_usuario'];
$mensagem = "";
$tipo_mensagem = "error";
$redirecionar = false;
$id_perfil = $_SESSION['usuario']['idperfil'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmacao = $_POST['confirmacao'] ?? '';

    // 1. Buscar hash atual
    $stmt = $conexao->prepare("SELECT senha_hash FROM usuario WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();
    $stmt->close();

    if (!$usuario || !password_verify($senha_atual, $usuario['senha_hash'])) {
        $mensagem = "The current password is incorrect.";
    } elseif ($nova_senha !== $confirmacao) {
        $mensagem = "The new password and the confirmation do not match.";
    } else {
        // 2. Atualizar senha
        $nova_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

        $stmt = $conexao->prepare("UPDATE usuario SET senha_hash = ?, primeira_senha = 0 WHERE id_usuario = ?");
        $stmt->bind_param("si", $nova_hash, $id_usuario);
        $stmt->execute();
        $stmt->close();

        // 3. Registrar no histórico
        $stmt_hist = $conexao->prepare("INSERT INTO historico_senhas (id_usuario, senha_hash) VALUES (?, ?)");
        $stmt_hist->bind_param("is", $id_usuario, $nova_hash);
        $stmt_hist->execute();
        $stmt_hist->close();

        $mensagem = "Password updated successfully! You will be logged out to apply changes.";
        $tipo_mensagem = "success";
        $redirecionar = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Change Password</title>
    <link rel="stylesheet" href="css/admin.css">
       <link rel="stylesheet" href="css/basico.css">
         <script src="js/darkmode1.js"></script>
          <script src="js/dropdown2.js"></script>
                <script src="js/mostrarSenha.js"></script>

                <style>
.editar{
background: #ff6600;
color: #fff;
border: none;
}

.editar:hover{
    background: #be5006ff;
    transform:scale(1.1);
}

.container{

    margin-left: 240px;
}

       .topbar {
            width: 100%;
            height: 40px;
            background: #ecf0f1;
            
            display: flex;
            align-items: center;

            padding: 0 20px;
            position: fixed; /* fixa no topo */
            top: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .topbar a {
            color: #d32f2f;
            margin-left: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .topbar a:hover {
          background: #ecf0f1;
  transform: translateX(2px);
  border-left: 3px solid #d32f2f;
        }

            h2 {
              text-align: center;
             margin-top: 60px; /* para não ficar atrás da topbar */
          }
                </style>
</head>
<body>




   

  <nav class="topbar">

      <?php if($id_perfil==1):?> 
        <a href="dashboard.php">Back</a>
     <?php else : ?>
<a href="index.php">Back</a>

  <?php endif; ?>
            
</nav>

 



<h2>Change Password</h2>

    <?php if (!empty($mensagem)): ?>
        <div class="mensagem <?= $tipo_mensagem ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>
<form method="POST" action="">
  <label>Current password:</label>
  
  <div style="position: relative; display: flex; align-items: center; justify-content: center;">
    <input type="password" name="senha_atual" class="campo-senha-atual" required
           style="width: 100%; padding-right: 35px; box-sizing: border-box;">
    <img src="icones/olho_fechado1.png"
         alt="Show current password"
         class="toggle-senha"
         data-target="campo-senha-atual"
         style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
  </div>

  <label>New password:</label>
  <div style="position: relative; display: flex; align-items: center; justify-content: center;">
    <input type="password" name="nova_senha" class="campo-senha-nova" required
           style="width: 100%; padding-right: 35px; box-sizing: border-box;">
    <img src="icones/olho_fechado1.png"
         alt="Show new password"
         class="toggle-senha"
         data-target="campo-senha-nova"
         style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
  </div>

  <label>Confirm new password:</label>
  <div style="position: relative; display: flex; align-items: center; justify-content: center;">
    <input type="password" name="confirmacao" class="campo-senha-confirmacao" required
           style="width: 100%; padding-right: 35px; box-sizing: border-box;">
    <img src="icones/olho_fechado1.png"
         alt="Show password confirmation"
         class="toggle-senha"
         data-target="campo-senha-confirmacao"
         style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
  </div>

  <input class="editar"  type="submit" value="Update Password">
</form>




<?php if ($redirecionar): ?>
<script>
    setTimeout(() => {
        window.location.href = 'logout.php';
    }, 3000);
</script>
<?php endif; ?>
</body>
</html>