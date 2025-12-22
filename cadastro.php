<?php 
//cadastro.php
// 1. INCLUSÃO DA CONEXÃO NO TOPO
// Isso permite que o SELECT de idiomas seja executado mesmo que o formulário não seja submetido (método GET).
session_save_path('/tmp'); 
include "conexao.php"; 

$mensagem = "";
$redirecionar = false;

// ----------------------------------------------------------------------
// LÓGICA DE PROCESSAMENTO (POST)
// ----------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // A conexão já está incluída no topo

    $nome       = htmlspecialchars(trim($_POST['nome']));
    $apelido    = htmlspecialchars(trim($_POST['apelido']));

    $senha      = trim($_POST['senha']);
    $conf       = htmlspecialchars(trim($_POST['conf']));


    // Verificação dos campos obrigatórios
    if (empty($nome) || empty($apelido) || empty($senha) || empty($conf)) {
        $mensagem = "⚠️ All fields are required!";
    } 
    else if ($senha != $conf) {
        $mensagem = "❌ The password and the confirmation do not match.";
    } 
       elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{6,}$/', $senha)) {
        $mensagem = "❌ The password must have at least 6 characters, one uppercase letter, one lowercase letter, and one number.";
    }
    else { 
        // Criptografa a senha definida pelo usuário
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        // 2. MODIFICAÇÃO DA QUERY PARA INCLUIR id_idioma
        // Adicionamos 'id_idioma' na lista de colunas e um novo placeholder '?' na lista de VALUES.
        $sql = "INSERT INTO usuario (nome, apelido, senha_hash, idperfil, primeira_senha) 
                VALUES (?, ?, ?, 3, 0)";
        
        $stmt = $conexao->prepare($sql);
        // O tipo de bind_param é 'sssssi' (string, string, string, string, string, integer)
        $stmt->bind_param("sss", $nome, $apelido, $senha_hash);

        if ($stmt->execute()) {
            $mensagem = "✅ Registration successful! Redirecting to login screen...";
            $redirecionar = true;
        } 
        $stmt->close();
        // REMOVIDA/COMENTADA: Se você fechar a conexão aqui, ela não estará disponível para a consulta de idiomas abaixo.
        // $conexao->close(); 
    }
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register</title>
    <meta charset="UTF-8">
    <meta name="viewport"  content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

          <script src="logout_auto.js"></script>
               <script src="js/mostrarSenha.js"></script>
               <link rel="stylesheet" href="css/admin.css">
    
    <style>
          body {
              font-family: Arial, sans-serif;
              background: #f5f5f5;
              padding: 20px;
          }

          h2 {
              text-align: center;
              color: #333;
          }

          form {
              max-width: 500px;
              margin: auto;
              background: white;
              padding: 30px;
              border-radius: 10px;
              box-shadow: 0 0 10px #ccc;
          }

          label {
              display: block;
              margin-bottom: 5px;
              font-weight: bold;
          }

          input, select {
              width: 100%;
              padding: 10px;
              margin-bottom: 15px;
              border-radius: 5px;
              border: 1px solid #aaa;
          }

          button {
              width: 100%;
              padding: 12px;
              background-color: #d32f2f;

              

              color: white;
              border: none;
              border-radius: 5px;
              font-size: 16px;
              cursor: pointer;
          }

          button:hover {
               background-color: #b71c1c;

          }
           
           .mensagem {
               max-width: 500px;
               margin: 20px auto;
               padding: 15px;
               border-radius: 8px;
               font-weight: bold;
           }

           .mensagem.success {
               background-color: #d4edda;
               color: #155724;
           }

           .mensagem.error {
               background-color: #f8d7da;
               color: #721c24;
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
    </style>
</head>
<body>

<nav class="topbar">

              <a href="login.php">Back to Login</a>
            
</nav>



    <?php if ($mensagem): ?>
        <div class="mensagem <?= str_contains($mensagem, '✅') ? 'success' : 'error' ?>">
            <?= $mensagem ?>
        </div>
    <?php endif; ?> <br><br>

    <h2>User Registration</h2>
    <form method="post" action="">
        <label>Name:</label>
        <input type="text" name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"><br>

        <label>Surname:</label>
        <input type="text" name="apelido" required value="<?= htmlspecialchars($_POST['apelido'] ?? '') ?>"><br>

      
        <label>Password:</label>
        <div style="position: relative; display: flex; align-items: center; justify-content: center;">
            <input type="password" name="senha" class="campo-senha-nova" required
                   style="width: 100%; padding-right: 35px; box-sizing: border-box;">
            <img src="icones/olho_fechado1.png"
                 alt="Show new password"
                 class="toggle-senha"
                 data-target="campo-senha-nova"
                 style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
        </div>

        <label>Confirm your password:</label>
        <div style="position: relative; display: flex; align-items: center; justify-content: center;">
            <input type="password" name="conf" class="campo-senha-confirmacao" required
                   style="width: 100%; padding-right: 35px; box-sizing: border-box;">
            <img src="icones/olho_fechado1.png"
                 alt="Show password confirmation"
                 class="toggle-senha"
                 data-target="campo-senha-confirmacao"
                 style="position: absolute; right: 10px; cursor: pointer; width: 22px; opacity: 0.8;">
        </div><br><br>

        <button type="submit">Register</button><br><br>
    </form>

    <?php if ($redirecionar): ?>
<script>
    // Redireciona em 3 segundos
    setTimeout(() => {
        window.location.href = 'login.php';
    }, 3000);
</script>
<?php endif; ?>


</body>
</html>