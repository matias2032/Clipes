<?php
// Headers CORS - DEVE SER A PRIMEIRA COISA NO ARQUIVO
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: text/html; charset=UTF-8");

// Responder OPTIONS e encerrar
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Continuar com o resto do código apenas se não for OPTIONS
include "verifica_login.php";
include "conexao.php";
include "info_usuario.php";
include "vercel_blob_upload.php"; // ← MUDANÇA: usar Vercel Blob em vez de Cloudinary

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

$usuario = $_SESSION['usuario'];
$id_perfil = $usuario['id_perfil'] ?? null;
$mensagem = "";
$tipo_mensagem = "info";
$redirecionar = false;

// Carregar categorias
$categorias = $conexao->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_video = trim($_POST['nome_video'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $preco = floatval($_POST['preco'] ?? 0);
    $duracao = trim($_POST['duracao'] ?? '');
    $categorias_selecionadas = $_POST['categorias'] ?? [];
    
    // Receber dados em Base64
    $previa_base64 = $_POST['video_previa_base64'] ?? '';
    $imagem_base64 = $_POST['imagem_base64'] ?? '';
    
    // Validação
    if (empty($nome_video) || empty($categorias_selecionadas)) {
        $mensagem = "⚠️ Nome do vídeo e pelo menos uma categoria são obrigatórios.";
        $tipo_mensagem = "error";
    } elseif (empty($previa_base64)) {
        $mensagem = "⚠️ A prévia do vídeo é obrigatória.";
        $tipo_mensagem = "error";
    } elseif (empty($imagem_base64)) {
        $mensagem = "⚠️ A imagem de destaque é obrigatória.";
        $tipo_mensagem = "error";
    } else {
        $conexao->begin_transaction();
        
        try {
            // Verificar configuração do Vercel Blob
            if (!isVercelBlobConfigured()) {
                throw new Exception("Vercel Blob não configurado. Adicione BLOB_READ_WRITE_TOKEN nas variáveis de ambiente.");
            }
            
            // Detectar tipos MIME
            $videoMimeType = detectMimeTypeFromBase64($previa_base64);
            $imageMimeType = detectMimeTypeFromBase64($imagem_base64);
            
            // Validar tipos de arquivo
            if (!isValidVideo($videoMimeType)) {
                throw new Exception("Formato de vídeo não permitido. Use MP4, WebM ou OGG.");
            }
            
            if (!isValidImage($imageMimeType)) {
                throw new Exception("Formato de imagem não permitido. Use JPG, PNG ou WebP.");
            }
            
            // Validar tamanhos
            $videoSize = getBase64FileSize($previa_base64);
            $imageSize = getBase64FileSize($imagem_base64);
            
            if ($videoSize > 100 * 1024 * 1024) { // 100MB
                throw new Exception("Vídeo muito grande. Máximo: 100MB (atual: " . formatFileSize($videoSize) . ")");
            }
            
            if ($imageSize > 5 * 1024 * 1024) { // 5MB
                throw new Exception("Imagem muito grande. Máximo: 5MB (atual: " . formatFileSize($imageSize) . ")");
            }
            
            // Gerar nomes de arquivo únicos
            $videoFilename = generateSafeFilename('preview.mp4', 'video');
            $imageFilename = generateSafeFilename('thumbnail.jpg', 'image');
            
            // Upload da prévia para Vercel Blob
            $caminho_previa = uploadToVercelBlobBase64($previa_base64, $videoFilename, $videoMimeType);
            
            // Upload da imagem para Vercel Blob
            $caminho_imagem = uploadToVercelBlobBase64($imagem_base64, $imageFilename, $imageMimeType);
            
            // Inserir vídeo
            $sql_video = "INSERT INTO video (nome_video, descricao, preco, duracao, caminho_previa, id_usuario) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_video = $conexao->prepare($sql_video);
            $stmt_video->bind_param("ssdssi", $nome_video, $descricao, $preco, $duracao, $caminho_previa, $usuario['id_usuario']);
            $stmt_video->execute();
            $id_video = $stmt_video->insert_id;
            
            // Inserir categorias
            $stmt_cat = $conexao->prepare("INSERT INTO video_categoria (id_video, id_categoria) VALUES (?, ?)");
            foreach ($categorias_selecionadas as $id_categoria) {
                $stmt_cat->bind_param("ii", $id_video, $id_categoria);
                $stmt_cat->execute();
            }
            
            // Inserir imagem
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
            
            // Log do erro para debug
            error_log("Erro ao cadastrar vídeo: " . $e->getMessage());
        }
    }
}
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
        .drop-zone-text { color: #7f8c8d; font-size: 1.1em; margin-bottom: 10px; }
        .file-input { display: none; }
        .file-name { font-weight: bold; color: #27ae60; margin-top: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .checkbox-group { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; }
        .preview-container { margin-top: 15px; }
        .preview-container img { max-width: 300px; border-radius: 8px; }
        .preview-container video { max-width: 500px; border-radius: 8px; }
        .upload-progress {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 5px;
        }
        .file-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
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
                
                <input type="hidden" name="video_previa_base64" id="video_previa_base64">
                <input type="hidden" name="imagem_base64" id="imagem_base64">
                
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
                        <div class="preview-container" id="previewPrevia"></div>
                        <div class="upload-progress" id="progressPrevia"></div>
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
                        <div class="preview-container" id="previewImagem"></div>
                        <div class="upload-progress" id="progressImagem"></div>
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
        // Drag and Drop para Prévia
        setupDropZone('dropZonePrevia', 'video_previa', 'fileNamePrevia', 'fileInfoPrevia', 'previewPrevia', 'video', 'video_previa_base64', 'progressPrevia');
        
        // Drag and Drop para Imagem
        setupDropZone('dropZoneImagem', 'imagem_destaque', 'fileNameImagem', 'fileInfoImagem', 'previewImagem', 'image', 'imagem_base64', 'progressImagem');

        function setupDropZone(dropZoneId, inputId, fileNameId, fileInfoId, previewId, type, hiddenInputId, progressId) {
            const dropZone = document.getElementById(dropZoneId);
            const fileInput = document.getElementById(inputId);
            const fileNameDisplay = document.getElementById(fileNameId);
            const fileInfoDisplay = document.getElementById(fileInfoId);
            const previewContainer = document.getElementById(previewId);
            const hiddenInput = document.getElementById(hiddenInputId);
            const progressDiv = document.getElementById(progressId);

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
                    handleFile(files[0], fileNameDisplay, fileInfoDisplay, previewContainer, type, hiddenInput, progressDiv);
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    handleFile(fileInput.files[0], fileNameDisplay, fileInfoDisplay, previewContainer, type, hiddenInput, progressDiv);
                }
            });
        }

        function handleFile(file, nameDisplay, infoDisplay, previewContainer, type, hiddenInput, progressDiv) {
            nameDisplay.textContent = `📁 ${file.name}`;
            infoDisplay.textContent = `Tamanho: ${formatFileSize(file.size)} | Tipo: ${file.type}`;
            progressDiv.style.display = 'block';
            progressDiv.textContent = 'Processando arquivo...';
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const base64Data = e.target.result;
                hiddenInput.value = base64Data;
                
                // Mostrar preview
                previewContainer.innerHTML = '';
                if (type === 'video') {
                    const video = document.createElement('video');
                    video.src = base64Data;
                    video.controls = true;
                    video.style.maxWidth = '100%';
                    previewContainer.appendChild(video);
                } else if (type === 'image') {
                    const img = document.createElement('img');
                    img.src = base64Data;
                    img.style.maxWidth = '100%';
                    previewContainer.appendChild(img);
                }
                
                progressDiv.textContent = '✅ Arquivo pronto para envio ao Vercel Blob';
                setTimeout(() => { progressDiv.style.display = 'none'; }, 2000);
            };
            
            reader.onerror = function() {
                progressDiv.textContent = '❌ Erro ao processar arquivo';
                progressDiv.style.background = '#ffebee';
            };
            
            reader.readAsDataURL(file);
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
        }

        // Validação do formulário
        document.getElementById('formCadastro').addEventListener('submit', function(e) {
            const videoBase64 = document.getElementById('video_previa_base64').value;
            const imagemBase64 = document.getElementById('imagem_base64').value;
            
            if (!videoBase64 || !imagemBase64) {
                e.preventDefault();
                alert('Por favor, selecione a prévia do vídeo e a imagem de destaque.');
                return false;
            }
            
            document.getElementById('btnSubmit').disabled = true;
            document.getElementById('btnSubmit').textContent = '📤 Enviando para Vercel Blob...';
        });
    </script>
</body>
</html>