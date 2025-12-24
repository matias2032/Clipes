<?php
/**
 * editar_video.php
 * VERSÃO CLIENT-SIDE UPLOAD
 */

include "verifica_login.php";
include "conexao.php";
include "info_usuario.php";

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: text/html; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$id_perfil = $usuario['idperfil'] ?? null;

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

// Incluir biblioteca para deletar arquivos antigos
include "vercel_blob_upload.php";

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

// Carregar categorias atuais
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
    
    // URLs dos novos arquivos (se houver)
    $nova_previa_url = trim($_POST['video_url'] ?? '');
    $nova_imagem_url = trim($_POST['imagem_url'] ?? '');
    
    $houveAlteracao = (
        $nome_video !== $video['nome_video'] ||
        $descricao !== $video['descricao'] ||
        $preco != $video['preco'] ||
        $duracao !== $video['duracao'] ||
        array_diff($categorias_selecionadas, $categorias_atuais) ||
        array_diff($categorias_atuais, $categorias_selecionadas) ||
        !empty($nova_previa_url) ||
        !empty($nova_imagem_url) ||
        $remover_previa ||
        $remover_imagem
    );
    
    if (!$houveAlteracao) {
        $mensagem = "Nenhuma alteração foi feita.";
        $tipo_mensagem = "error";
    } else {
        $conexao->begin_transaction();
        
        try {
            $caminho_previa_final = $video['caminho_previa'];
            $caminho_imagem_final = $video['caminho_imagem'];
            
            // Processar prévia
            if ($remover_previa) {
                if (!empty($video['caminho_previa'])) {
                    try {
                        deleteFromVercelBlob($video['caminho_previa']);
                    } catch (Exception $e) {
                        error_log("Erro ao remover prévia antiga: " . $e->getMessage());
                    }
                }
                $caminho_previa_final = null;
            }
            
            if (!empty($nova_previa_url)) {
                if (!empty($video['caminho_previa'])) {
                    try {
                        deleteFromVercelBlob($video['caminho_previa']);
                    } catch (Exception $e) {
                        error_log("Erro ao remover prévia antiga: " . $e->getMessage());
                    }
                }
                $caminho_previa_final = $nova_previa_url;
            }
            
            // Processar imagem
            if ($remover_imagem) {
                if (!empty($video['caminho_imagem'])) {
                    try {
                        deleteFromVercelBlob($video['caminho_imagem']);
                    } catch (Exception $e) {
                        error_log("Erro ao remover imagem antiga: " . $e->getMessage());
                    }
                }
                $conexao->query("DELETE FROM video_imagem WHERE id_video = $id_video AND imagem_principal = 1");
                $caminho_imagem_final = null;
            }
            
            if (!empty($nova_imagem_url)) {
                if (!empty($video['caminho_imagem'])) {
                    try {
                        deleteFromVercelBlob($video['caminho_imagem']);
                    } catch (Exception $e) {
                        error_log("Erro ao remover imagem antiga: " . $e->getMessage());
                    }
                }
                
                $conexao->query("DELETE FROM video_imagem WHERE id_video = $id_video AND imagem_principal = 1");
                $stmtImg = $conexao->prepare("INSERT INTO video_imagem (id_video, caminho_imagem, imagem_principal) VALUES (?, ?, 1)");
                $stmtImg->bind_param("is", $id_video, $nova_imagem_url);
                $stmtImg->execute();
                $caminho_imagem_final = $nova_imagem_url;
            }
            
            // Atualizar vídeo
            $sql_update = "UPDATE video SET nome_video=?, descricao=?, preco=?, duracao=?, caminho_previa=? WHERE id_video=?";
            $stmt_up = $conexao->prepare($sql_update);
            $stmt_up->bind_param("ssdssi", $nome_video, $descricao, $preco, $duracao, $caminho_previa_final, $id_video);
            $stmt_up->execute();
            
            // Atualizar categorias
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
            
            // Recarregar dados
            $stmt->execute();
            $video = $stmt->get_result()->fetch_assoc();
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
            error_log("Erro ao editar vídeo: " . $e->getMessage());
        }
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar Vídeo</title>
<link rel="stylesheet" href="../css/admin.css">
<script src="../js/darkmode2.js"></script>
<script src="../js/sidebar.js"></script>
<script src="../js/dropdown2.js"></script>

<style>
.drop-zone { width: 100%; min-height: 150px; padding: 20px; margin-bottom: 20px; text-align: center; border: 2px dashed #3498db; border-radius: 10px; background-color: #ecf0f1; transition: all 0.3s; }
.drop-zone.drag-over { background-color: #d0e7f7; border-color: #2980b9; }
.drop-zone.uploading { background-color: #fff3cd; border-color: #ffc107; }
.drop-zone.success { background-color: #d4edda; border-color: #28a745; }
.drop-zone.error { background-color: #f8d7da; border-color: #dc3545; }
.file-input { display: none; }
.file-name { font-weight: bold; color: #27ae60; margin-top: 10px; }
.file-info { font-size: 0.9em; color: #666; margin-top: 5px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
.checkbox-group { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
.checkbox-item { display: flex; align-items: center; gap: 8px; }
.preview-container { margin-top: 15px; }
.preview-container img, .preview-container video { max-width: 100%; border-radius: 8px; }
.current-file { background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
.current-file a { color: #27ae60; text-decoration: none; font-weight: bold; }
.upload-progress { margin-top: 10px; padding: 10px; background: #e8f5e9; border-radius: 5px; font-weight: bold; }
.progress-bar { width: 100%; height: 20px; background: #e0e0e0; border-radius: 10px; overflow: hidden; margin-top: 10px; }
.progress-fill { height: 100%; background: linear-gradient(90deg, #4caf50, #8bc34a); transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8em; font-weight: bold; }
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

    <form method="post" id="formEditar">
      
      <input type="hidden" name="video_url" id="video_url">
      <input type="hidden" name="imagem_url" id="imagem_url">
      
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
            <p>📹 <a href="<?= $video['caminho_previa'] ?>" target="_blank">Ver prévia atual (Vercel Blob)</a></p>
            <video src="<?= $video['caminho_previa'] ?>" controls style="max-width: 400px; border-radius: 8px;"></video>
            <br><br>
            <label><input type="checkbox" name="remover_previa"> Remover prévia atual</label>
          </div>
        <?php endif; ?>
        
        <input type="file" id="video_previa" accept="video/*" class="file-input">
        <div class="drop-zone" id="dropZonePrevia">
          <p>Arraste nova prévia aqui ou <button type="button" onclick="document.getElementById('video_previa').click()">clique para escolher</button></p>
          <p id="fileNamePrevia" class="file-name"></p>
          <p id="fileInfoPrevia" class="file-info"></p>
          <div class="upload-progress" id="progressPrevia" style="display:none;"></div>
          <div class="progress-bar" id="progressBarPrevia" style="display:none;">
            <div class="progress-fill" id="progressFillPrevia">0%</div>
          </div>
          <div class="preview-container" id="previewPrevia"></div>
        </div>
      </div>

      <div class="form-group">
        <label>Imagem de Destaque</label>
        <?php if (!empty($video['caminho_imagem'])): ?>
          <div class="current-file">
            <p>🖼️ <a href="<?= $video['caminho_imagem'] ?>" target="_blank">Ver imagem atual (Vercel Blob)</a></p>
            <img src="<?= $video['caminho_imagem'] ?>" style="max-width: 300px; border-radius: 8px;">
            <br><br>
            <label><input type="checkbox" name="remover_imagem"> Remover imagem atual</label>
          </div>
        <?php endif; ?>
        
        <input type="file" id="imagem_destaque" accept="image/*" class="file-input">
        <div class="drop-zone" id="dropZoneImagem">
          <p>Arraste nova imagem aqui ou <button type="button" onclick="document.getElementById('imagem_destaque').click()">clique para escolher</button></p>
          <p id="fileNameImagem" class="file-name"></p>
          <p id="fileInfoImagem" class="file-info"></p>
          <div class="upload-progress" id="progressImagem" style="display:none;"></div>
          <div class="progress-bar" id="progressBarImagem" style="display:none;">
            <div class="progress-fill" id="progressFillImagem">0%</div>
          </div>
          <div class="preview-container" id="previewImagem"></div>
        </div>
      </div>

      <button type="submit" id="btnSubmit">💾 Salvar Alterações</button>
    </form>
  </div>
</div>

<?php if ($redirecionar): ?>
<script>
    setTimeout(() => { window.location.href = 'gerenciar_videos.php'; }, 2000);
</script>
<?php endif; ?>

<script>
setupDropZone('dropZonePrevia', 'video_previa', 'fileNamePrevia', 'fileInfoPrevia', 'previewPrevia', 'video', 'video_url', 'progressPrevia', 'progressBarPrevia', 'progressFillPrevia');
setupDropZone('dropZoneImagem', 'imagem_destaque', 'fileNameImagem', 'fileInfoImagem', 'previewImagem', 'image', 'imagem_url', 'progressImagem', 'progressBarImagem', 'progressFillImagem');

function setupDropZone(dropZoneId, inputId, fileNameId, fileInfoId, previewId, type, urlInputId, progressId, progressBarId, progressFillId) {
    const dropZone = document.getElementById(dropZoneId);
    const fileInput = document.getElementById(inputId);
    const fileNameDisplay = document.getElementById(fileNameId);
    const fileInfoDisplay = document.getElementById(fileInfoId);
    const previewContainer = document.getElementById(previewId);
    const urlInput = document.getElementById(urlInputId);
    const progressDiv = document.getElementById(progressId);
    const progressBar = document.getElementById(progressBarId);
    const progressFill = document.getElementById(progressFillId);

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
            handleFileClientUpload(files[0], fileNameDisplay, fileInfoDisplay, previewContainer, type, urlInput, progressDiv, progressBar, progressFill, dropZone);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            handleFileClientUpload(fileInput.files[0], fileNameDisplay, fileInfoDisplay, previewContainer, type, urlInput, progressDiv, progressBar, progressFill, dropZone);
        }
    });
}

async function handleFileClientUpload(file, nameDisplay, infoDisplay, previewContainer, type, urlInput, progressDiv, progressBar, progressFill, dropZone) {
    nameDisplay.textContent = `📁 ${file.name}`;
    infoDisplay.textContent = `Tamanho: ${formatFileSize(file.size)} | Tipo: ${file.type}`;
    
    dropZone.classList.add('uploading');
    progressDiv.style.display = 'block';
    progressBar.style.display = 'block';
    progressDiv.textContent = '🔄 Preparando upload...';
    progressFill.style.width = '10%';
    progressFill.textContent = '10%';

    try {
        const response = await fetch('get_upload_url.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                filename: file.name,
                contentType: file.type
            })
        });

        if (!response.ok) {
            throw new Error('Erro ao obter URL de upload');
        }

        const { uploadUrl, token, publicUrl } = await response.json();
        
        progressFill.style.width = '30%';
        progressFill.textContent = '30%';
        progressDiv.textContent = '📤 Enviando para Vercel Blob...';

        const uploadResponse = await fetch(uploadUrl, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': file.type,
                'x-content-type': file.type
            },
            body: file
        });

        if (!uploadResponse.ok) {
            throw new Error(`Upload falhou: ${uploadResponse.status}`);
        }

        const result = await uploadResponse.json();
        
        progressFill.style.width = '100%';
        progressFill.textContent = '100%';
        
        urlInput.value = result.url || publicUrl;
        
        dropZone.classList.remove('uploading');
        dropZone.classList.add('success');
        progressDiv.textContent = '✅ Upload concluído!';

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

        setTimeout(() => {
            progressBar.style.display = 'none';
            progressDiv.style.display = 'none';
        }, 3000);
        
    } catch (error) {
        dropZone.classList.remove('uploading');
        dropZone.classList.add('error');
        progressDiv.textContent = `❌ Erro: ${error.message}`;
        progressDiv.style.background = '#ffebee';
        console.error('Erro no upload:', error);
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

document.getElementById('formEditar').addEventListener('submit', function() {
    document.getElementById('btnSubmit').disabled = true;
    document.getElementById('btnSubmit').textContent = '💾 Salvando...';
});
</script>

</body>
</html>