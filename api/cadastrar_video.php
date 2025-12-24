<?php
/**
 * cadastrar_video.php
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
$mensagem = "";
$tipo_mensagem = "info";
$redirecionar = false;

$categorias = $conexao->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_video = trim($_POST['nome_video'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = floatval($_POST['preco'] ?? 0);
    $duracao = trim($_POST['duracao'] ?? '');
    $categorias_selecionadas = $_POST['categorias'] ?? [];
    
    // URLs dos arquivos já carregados no Vercel Blob
    $caminho_previa = trim($_POST['video_url'] ?? '');
    $caminho_imagem = trim($_POST['imagem_url'] ?? '');
    
    if (empty($nome_video) || empty($categorias_selecionadas)) {
        $mensagem = "⚠️ Nome do vídeo e pelo menos uma categoria são obrigatórios.";
        $tipo_mensagem = "error";
    } elseif (empty($caminho_previa)) {
        $mensagem = "⚠️ A prévia do vídeo é obrigatória.";
        $tipo_mensagem = "error";
    } elseif (empty($caminho_imagem)) {
        $mensagem = "⚠️ A imagem de destaque é obrigatória.";
        $tipo_mensagem = "error";
    } else {
        $conexao->begin_transaction();
        
        try {
            $sql_video = "INSERT INTO video (nome_video, descricao, preco, duracao, caminho_previa, id_usuario) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_video = $conexao->prepare($sql_video);
            $stmt_video->bind_param("ssdssi", $nome_video, $descricao, $preco, $duracao, $caminho_previa, $usuario['id_usuario']);
            $stmt_video->execute();
            $id_video = $stmt_video->insert_id;
            
            $stmt_cat = $conexao->prepare("INSERT INTO video_categoria (id_video, id_categoria) VALUES (?, ?)");
            foreach ($categorias_selecionadas as $id_categoria) {
                $stmt_cat->bind_param("ii", $id_video, $id_categoria);
                $stmt_cat->execute();
            }
            
            $stmt_img = $conexao->prepare("INSERT INTO video_imagem (id_video, caminho_imagem, imagem_principal) VALUES (?, ?, 1)");
            $stmt_img->bind_param("is", $id_video, $caminho_imagem);
            $stmt_img->execute();
            
            $conexao->commit();
            
            $mensagem = "✅ Vídeo cadastrado com sucesso no Vercel Blob!";
            $tipo_mensagem = "success";
            $redirecionar = true;
            
        } catch (Exception $e) {
            $conexao->rollback();
            $mensagem = "❌ Erro: " . $e->getMessage();
            $tipo_mensagem = "error";
            error_log("Erro ao cadastrar vídeo: " . $e->getMessage());
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
    <title>Cadastrar Vídeo</title>
    <link rel="stylesheet" href="../css/admin.css">
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
        .drop-zone.uploading { background-color: #fff3cd; border-color: #ffc107; }
        .drop-zone.success { background-color: #d4edda; border-color: #28a745; }
        .drop-zone.error { background-color: #f8d7da; border-color: #dc3545; }
        .file-input { display: none; }
        .file-name { font-weight: bold; color: #27ae60; margin-top: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .checkbox-group { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; }
        .preview-container { margin-top: 15px; }
        .preview-container img, .preview-container video { max-width: 100%; border-radius: 8px; }
        .upload-progress {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 5px;
            font-weight: bold;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4caf50, #8bc34a);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8em;
            font-weight: bold;
        }
        .file-info { font-size: 0.9em; color: #666; margin-top: 5px; }
    </style>
</head>
<body>

    <button class="menu-btn">☰</button>
    <div class="sidebar-overlay"></div>

    <sidebar class="sidebar">
        <br><br>
        <a href="gerenciar_videos.php">Voltar à área de Vídeos</a>
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
            <h1>Cadastrar Novo Vídeo</h1>

            <?php if (!empty($mensagem)): ?>
                <div class="mensagem <?= htmlspecialchars($tipo_mensagem) ?>">
                    <?= htmlspecialchars($mensagem) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" id="formCadastro" class="form-container">
                
                <input type="hidden" name="video_url" id="video_url">
                <input type="hidden" name="imagem_url" id="imagem_url">
                
                <div class="form-group">
                    <label for="nome_video">Nome do Vídeo *</label>
                    <input type="text" name="nome_video" id="nome_video" value="<?= htmlspecialchars($_POST['nome_video'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição</label>
                    <textarea name="descricao" id="descricao" rows="4"><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="preco">Preço (opcional)</label>
                    <input type="number" name="preco" id="preco" step="0.01" min="0" value="<?= htmlspecialchars($_POST['preco'] ?? '0.00') ?>">
                </div>

                <div class="form-group">
                    <label for="duracao">Duração (HH:MM:SS)</label>
                    <input type="text" name="duracao" id="duracao" placeholder="00:03:45" value="<?= htmlspecialchars($_POST['duracao'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Categorias *</label>
                    <div class="checkbox-group">
                        <?php $categorias->data_seek(0); while ($cat = $categorias->fetch_assoc()): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="categorias[]" id="cat_<?= $cat['id_categoria'] ?>" value="<?= $cat['id_categoria'] ?>"
                                    <?= isset($_POST['categorias']) && in_array($cat['id_categoria'], $_POST['categorias']) ? 'checked' : '' ?>>
                                <label for="cat_<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nome_categoria']) ?></label>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Prévia do Vídeo * (MP4, WebM ou OGG - Máx: 100MB)</label>
                    <input type="file" id="video_previa" accept="video/*" class="file-input" required>
                    <div class="drop-zone" id="dropZonePrevia">
                        <div class="drop-zone-text">Arraste e solte a prévia aqui</div>
                        <button type="button" onclick="document.getElementById('video_previa').click()">
                            Ou Clique para Escolher
                        </button>
                        <p class="file-name" id="fileNamePrevia"></p>
                        <p class="file-info" id="fileInfoPrevia"></p>
                        <div class="upload-progress" id="progressPrevia" style="display:none;"></div>
                        <div class="progress-bar" id="progressBarPrevia" style="display:none;">
                            <div class="progress-fill" id="progressFillPrevia">0%</div>
                        </div>
                        <div class="preview-container" id="previewPrevia"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Imagem de Destaque * (JPG, PNG ou WebP - Máx: 5MB)</label>
                    <input type="file" id="imagem_destaque" accept="image/*" class="file-input" required>
                    <div class="drop-zone" id="dropZoneImagem">
                        <div class="drop-zone-text">Arraste e solte a imagem aqui</div>
                        <button type="button" onclick="document.getElementById('imagem_destaque').click()">
                            Ou Clique para Escolher
                        </button>
                        <p class="file-name" id="fileNameImagem"></p>
                        <p class="file-info" id="fileInfoImagem"></p>
                        <div class="upload-progress" id="progressImagem" style="display:none;"></div>
                        <div class="progress-bar" id="progressBarImagem" style="display:none;">
                            <div class="progress-fill" id="progressFillImagem">0%</div>
                        </div>
                        <div class="preview-container" id="previewImagem"></div>
                    </div>
                </div>

                <button type="submit" id="btnSubmit">Cadastrar Vídeo</button>
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
                // 1. Obter URL de upload
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

                // 2. Upload direto para Vercel Blob
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
                
                // Armazenar URL retornada
                urlInput.value = result.url || publicUrl;
                
                dropZone.classList.remove('uploading');
                dropZone.classList.add('success');
                progressDiv.textContent = '✅ Upload concluído!';

                // Preview
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

        document.getElementById('formCadastro').addEventListener('submit', function(e) {
            const videoUrl = document.getElementById('video_url').value;
            const imagemUrl = document.getElementById('imagem_url').value;
            
            if (!videoUrl || !imagemUrl) {
                e.preventDefault();
                alert('Por favor, aguarde o upload dos arquivos terminar antes de enviar o formulário.');
                return false;
            }
            
            document.getElementById('btnSubmit').disabled = true;
            document.getElementById('btnSubmit').textContent = '💾 Salvando informações...';
        });
    </script>
</body>
</html>