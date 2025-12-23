<?php
// editar_video.php
include "verifica_login.php";
include "conexao.php";
include "info_usuario.php";

// --- INTEGRAÇÃO CLOUDINARY ---
include "cloudinary_upload.php"; 

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$id_perfil = $usuario['idperfil'] ?? null; // Verifique se no seu sistema é idperfil ou id_perfil

// Apenas Admin pode editar
if ($id_perfil !== 1) {
    header("Location: ver_videos.php");
    exit;
}

$id_video = intval($_GET['id_video'] ?? 0);
if ($id_video <= 0) {
    header("Location: gerenciar_videos.php");
    exit;
}

$mensagem = "";
$tipo_mensagem = "";
$redirecionar = false;

// Carregar dados do vídeo
$stmt = $conexao->prepare("SELECT v.*, vi.caminho_imagem 
                          FROM video v 
                          LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = 1 
                          WHERE v.id_video = ?");
$stmt->bind_param("i", $id_video);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();

if (!$video) {
    die("Vídeo não encontrado.");
}

// Carregar categorias atuais do vídeo
$stmtCatAtual = $conexao->prepare("SELECT id_categoria FROM video_categoria WHERE id_video = ?");
$stmtCatAtual->bind_param("i", $id_video);
$stmtCatAtual->execute();
$resCatAtual = $stmtCatAtual->get_result();
$categorias_atuais = [];
while ($row = $resCatAtual->fetch_assoc()) {
    $categorias_atuais[] = $row['id_categoria'];
}

// Carregar todas as categorias
$categorias = $conexao->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria");

// Processar formulário
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nome_video = trim($_POST['nome_video']);
    $descricao = trim($_POST['descricao']);
    $preco = floatval($_POST['preco']);
    $duracao = trim($_POST['duracao']);
    $categorias_selecionadas = $_POST['categorias'] ?? [];
    
    $remover_previa = isset($_POST['remover_previa']);
    $remover_imagem = isset($_POST['remover_imagem']);
    
    // Verificamos se foi enviado arquivo pelo nome original
    $nova_previa = $_FILES['video_previa']['name'] ?? "";
    $nova_imagem = $_FILES['imagem_destaque']['name'] ?? "";
    
    // Verificar alterações (Lógica mantida)
    $houveAlteracao = (
        $nome_video !== $video['nome_video'] ||
        $descricao !== $video['descricao'] ||
        $preco != $video['preco'] ||
        $duracao !== $video['duracao'] ||
        array_diff($categorias_selecionadas, $categorias_atuais) ||
        array_diff($categorias_atuais, $categorias_selecionadas) ||
        !empty($nova_previa) ||
        !empty($nova_imagem) ||
        $remover_previa ||
        $remover_imagem
    );
    
    if (!$houveAlteracao) {
        $mensagem = "Nenhuma alteração foi feita.";
        $tipo_mensagem = "error";
    } else {
        $conexao->begin_transaction();
        
        try {
            // Inicializa com os caminhos atuais
            $caminho_previa_final = $video['caminho_previa'];
            $caminho_imagem_final = $video['caminho_imagem'];
            
            // --- LÓGICA DA PRÉVIA (VÍDEO) ---
            
            // 1. Se pediu para remover, limpa o caminho
            if ($remover_previa) {
                // Se você tiver função de deletar no Cloudinary, use aqui:
                // deleteFromCloudinary($caminho_previa_final, 'video');
                $caminho_previa_final = null;
            }
            
            // 2. Se enviou novo vídeo, faz upload no Cloudinary
            if (!empty($nova_previa) && $_FILES['video_previa']['error'] === UPLOAD_ERR_OK) {
                
                $tipos_video_permitidos = ['video/mp4', 'video/webm', 'video/ogg'];
                if (!in_array($_FILES['video_previa']['type'], $tipos_video_permitidos)) {
                    throw new Exception("Formato de vídeo não permitido.");
                }
                
                if ($_FILES['video_previa']['size'] > 100 * 1024 * 1024) {
                    throw new Exception("Prévia muito grande. Limite: 100MB.");
                }
                
                // Upload para a pasta 'videos/previas' no Cloudinary
                $url_novo_video = uploadToCloudinary($_FILES['video_previa']['tmp_name'], 'videos/previas', 'video');
                
                if ($url_novo_video) {
                    // Se existia um anterior e você quiser economizar espaço:
                    // if ($caminho_previa_final) deleteFromCloudinary($caminho_previa_final, 'video');
                    
                    $caminho_previa_final = $url_novo_video;
                } else {
                    throw new Exception("Falha no upload do vídeo para o Cloudinary.");
                }
            }
            
            // --- LÓGICA DA IMAGEM ---
            
            // 1. Se pediu para remover imagem
            if ($remover_imagem) {
                // if ($caminho_imagem_final) deleteFromCloudinary($caminho_imagem_final, 'image');
                $conexao->query("DELETE FROM video_imagem WHERE id_video = $id_video AND imagem_principal = 1");
                $caminho_imagem_final = null;
            }
            
            // 2. Se enviou nova imagem, faz upload no Cloudinary
            if (!empty($nova_imagem) && $_FILES['imagem_destaque']['error'] === UPLOAD_ERR_OK) {
                
                $tipos_imagem_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                if (!in_array($_FILES['imagem_destaque']['type'], $tipos_imagem_permitidos)) {
                    throw new Exception("Formato de imagem não permitido.");
                }
                
                if ($_FILES['imagem_destaque']['size'] > 5 * 1024 * 1024) {
                    throw new Exception("Imagem muito grande. Limite: 5MB.");
                }
                
                // Upload para a pasta 'videos/imagens' no Cloudinary
                $url_nova_imagem = uploadToCloudinary($_FILES['imagem_destaque']['tmp_name'], 'videos/imagens', 'image');
                
                if ($url_nova_imagem) {
                    // Remove registro antigo da tabela de imagens se existir (para substituir)
                    $conexao->query("DELETE FROM video_imagem WHERE id_video = $id_video AND imagem_principal = 1");
                    
                    // Inserir nova imagem com a URL do Cloudinary
                    $stmtImg = $conexao->prepare("INSERT INTO video_imagem (id_video, caminho_imagem, imagem_principal) VALUES (?, ?, 1)");
                    $stmtImg->bind_param("is", $id_video, $url_nova_imagem);
                    $stmtImg->execute();
                    
                    $caminho_imagem_final = $url_nova_imagem;
                } else {
                    throw new Exception("Falha no upload da imagem para o Cloudinary.");
                }
            }
            
            // --- ATUALIZAR DADOS GERAIS DO VÍDEO ---
            // Nota: Se a imagem mudou, já atualizamos na tabela video_imagem acima.
            // Aqui atualizamos video, preco, e o link da prévia.
            
            $sql_update = "UPDATE video SET nome_video=?, descricao=?, preco=?, duracao=?, caminho_previa=? WHERE id_video=?";
            $stmt_up = $conexao->prepare($sql_update);
            $stmt_up->bind_param("ssdssi", $nome_video, $descricao, $preco, $duracao, $caminho_previa_final, $id_video);
            $stmt_up->execute();
            
            // --- ATUALIZAR CATEGORIAS ---
            $conexao->query("DELETE FROM video_categoria WHERE id_video = $id_video");
            
            if (!empty($categorias_selecionadas)) {
                $stmtCat = $conexao->prepare("INSERT INTO video_categoria (id_video, id_categoria) VALUES (?, ?)");
                foreach ($categorias_selecionadas as $id_categoria) {
                    $stmtCat->bind_param("ii", $id_video, $id_categoria);
                    $stmtCat->execute();
                }
            }
            
            $conexao->commit();
            
            $mensagem = "✅ Vídeo atualizado com sucesso!";
            $tipo_mensagem = "success";
            $redirecionar = true;
            
            // Recarregar dados para exibir atualizado
            $stmt = $conexao->prepare("SELECT v.*, vi.caminho_imagem FROM video v 
                                      LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = 1 
                                      WHERE v.id_video = ?");
            $stmt->bind_param("i", $id_video);
            $stmt->execute();
            $video = $stmt->get_result()->fetch_assoc();
            
            // Recarregar categorias
            $stmtCatAtual->execute();
            $resCatAtual = $stmtCatAtual->get_result();
            $categorias_atuais = [];
            while ($row = $resCatAtual->fetch_assoc()) {
                $categorias_atuais[] = $row['id_categoria'];
            }
            
        } catch (Exception $e) {
            $conexao->rollback();
            $mensagem = "❌ Erro: " . $e->getMessage();
            $tipo_mensagem = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar Vídeo</title>
<link rel="stylesheet" href="../css/admin.css">
<script src="../logout_auto.js"></script>
<script src="../js/darkmode2.js"></script>
<script src="../js/sidebar.js"></script>
<script src="../js/dropdown2.js"></script>

<style>
.drop-zone {
    width: 100%; min-height: 150px; padding: 20px; margin-bottom: 20px;
    text-align: center; border: 2px dashed #3498db; border-radius: 10px;
    background-color: #ecf0f1; transition: all 0.3s;
}
.drop-zone.drag-over { background-color: #d0e7f7; border-color: #2980b9; }
.file-input { display: none; }
.file-name { font-weight: bold; color: #27ae60; margin-top: 10px; }
.checkbox-group { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.checkbox-item { display: flex; align-items: center; gap: 8px; }
.preview-container { margin-top: 15px; }
.preview-container img, .preview-container video { max-width: 100%; border-radius: 8px; }
.current-file { background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
.current-file a { color: #27ae60; text-decoration: none; font-weight: bold; }
</style>
</head>
<body>

<button class="menu-btn">☰</button>
<div class="sidebar-overlay"></div>

<sidebar class="sidebar">
<br><br>
  <a href="gerenciar_videos.php">Voltar à Gestão de Vídeos</a>
  <div class="sidebar-user-wrapper">
    <div class="sidebar-user" id="usuarioDropdown">
        <div class="usuario-avatar" style="background-color: <?= $corAvatar ?>;">
            <?= $iniciais ?>
        </div>
        <div class="usuario-dados">
            <div class="usuario-nome"><?= $nome ?></div>
            <div class="usuario-apelido"><?= $apelido ?></div>
        </div>
        <div class="usuario-menu" id="menuPerfil">
            <a href='editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>'>
                <img class="icone" src="../icones/user1.png" alt="Editar"> Editar Dados Pessoais
            </a>
            <a href="alterar_senha2.php">
                <img class="icone" src="../icones/cadeado1.png" alt="Alterar"> Alterar Senha
            </a>
            <a href="logout.php">
                <img class="iconelogout" src="../icones/logout1.png" alt="Logout"> Sair
            </a>
        </div>
    </div>
    <img class="dark-toggle" id="darkToggle" src="../icones/lua.png" alt="Modo Escuro">
  </div>
</sidebar>

<div class="content">
  <div class="main">
    <h1>Editar Vídeo</h1>

    <?php if ($mensagem): ?>
      <div class="mensagem <?= htmlspecialchars($tipo_mensagem) ?>"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      
      <div class="form-group">
        <label>Nome do Vídeo *</label>
        <input type="text" name="nome_video" value="<?= htmlspecialchars($video['nome_video']) ?>" required>
      </div>

      <div class="form-group">
        <label>Descrição</label>
        <textarea name="descricao" rows="4"><?= htmlspecialchars($video['descricao']) ?></textarea>
      </div>

      <div class="form-group">
        <label>Preço</label>
        <input type="number" name="preco" step="0.01" min="0" value="<?= $video['preco'] ?>">
      </div>

      <div class="form-group">
        <label>Duração (HH:MM:SS)</label>
        <input type="text" name="duracao" placeholder="00:03:45" value="<?= htmlspecialchars($video['duracao']) ?>">
      </div>

      <div class="form-group">
        <label>Categorias *</label>
        <div class="checkbox-group">
          <?php $categorias->data_seek(0); while ($cat = $categorias->fetch_assoc()): ?>
            <div class="checkbox-item">
              <input type="checkbox" name="categorias[]" id="cat_<?= $cat['id_categoria'] ?>" 
                     value="<?= $cat['id_categoria'] ?>"
                     <?= in_array($cat['id_categoria'], $categorias_atuais) ? 'checked' : '' ?>>
              <label for="cat_<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nome_categoria']) ?></label>
            </div>
          <?php endwhile; ?>
        </div>
      </div>

      <div class="form-group">
        <label>Prévia do Vídeo</label>
        <?php if (!empty($video['caminho_previa'])): ?>
          <div class="current-file">
            <p>📹 <a href="<?= $video['caminho_previa'] ?>" target="_blank">Ver prévia atual</a></p>
            <video src="<?= $video['caminho_previa'] ?>" controls style="max-width: 400px; border-radius: 8px;"></video>
            <br><br>
            <label><input type="checkbox" name="remover_previa"> Remover prévia atual</label>
          </div>
        <?php else: ?>
          <p><em>Nenhuma prévia anexada.</em></p>
        <?php endif; ?>
        
        <input type="file" name="video_previa" id="video_previa" accept="video/*" class="file-input">
        <div class="drop-zone" id="dropZonePrevia">
          <p>Arraste nova prévia aqui ou <button type="button" onclick="document.getElementById('video_previa').click()">clique para escolher</button></p>
          <p id="fileNamePrevia" class="file-name"></p>
          <div class="preview-container" id="previewPrevia"></div>
        </div>
      </div>

      <div class="form-group">
        <label>Imagem de Destaque</label>
        <?php if (!empty($video['caminho_imagem'])): ?>
          <div class="current-file">
            <p>🖼️ <a href="<?= $video['caminho_imagem'] ?>" target="_blank">Ver imagem atual</a></p>
            <img src="<?= $video['caminho_imagem'] ?>" style="max-width: 300px; border-radius: 8px;">
            <br><br>
            <label><input type="checkbox" name="remover_imagem"> Remover imagem atual</label>
          </div>
        <?php else: ?>
          <p><em>Nenhuma imagem anexada.</em></p>
        <?php endif; ?>
        
        <input type="file" name="imagem_destaque" id="imagem_destaque" accept="image/*" class="file-input">
        <div class="drop-zone" id="dropZoneImagem">
          <p>Arraste nova imagem aqui ou <button type="button" onclick="document.getElementById('imagem_destaque').click()">clique para escolher</button></p>
          <p id="fileNameImagem" class="file-name"></p>
          <div class="preview-container" id="previewImagem"></div>
        </div>
      </div>

      <button type="submit" style="background: #27ae60; color: white; padding: 15px 30px; 
                                   border: none; border-radius: 8px; cursor: pointer; 
                                   font-size: 1.1em; font-weight: bold;">
        💾 Salvar Alterações
      </button>
    </form>
  </div>
</div>

<?php if ($redirecionar): ?>
<script>
    setTimeout(() => { window.location.href = 'gerenciar_videos.php'; }, 2000);
</script>
<?php endif; ?>

<script>
// Drag and Drop Setup
setupDropZone('dropZonePrevia', 'video_previa', 'fileNamePrevia', 'previewPrevia', 'video');
setupDropZone('dropZoneImagem', 'imagem_destaque', 'fileNameImagem', 'previewImagem', 'image');

function setupDropZone(dropZoneId, inputId, fileNameId, previewId, type) {
    const dropZone = document.getElementById(dropZoneId);
    const fileInput = document.getElementById(inputId);
    const fileNameDisplay = document.getElementById(fileNameId);
    const previewContainer = document.getElementById(previewId);

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => { e.preventDefault(); e.stopPropagation(); }, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'), false);
    });

    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            displayFile(files[0], fileNameDisplay, previewContainer, type);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            displayFile(fileInput.files[0], fileNameDisplay, previewContainer, type);
        }
    });
}

function displayFile(file, nameDisplay, previewContainer, type) {
    nameDisplay.textContent = `Novo arquivo: ${file.name}`;
    previewContainer.innerHTML = '';

    if (type === 'video') {
        const video = document.createElement('video');
        video.src = URL.createObjectURL(file);
        video.controls = true;
        video.style.maxWidth = '100%';
        previewContainer.appendChild(video);
    } else if (type === 'image') {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.style.maxWidth = '100%';
        previewContainer.appendChild(img);
    }
}
</script>

</body>
</html>