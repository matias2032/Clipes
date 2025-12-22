<?php
// cadastrar_video.php
include "verifica_login.php";
include  "conexao.php";
include "info_usuario.php";

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
    
    $arquivo_previa = $_FILES['video_previa'] ?? null;
    $arquivo_imagem = $_FILES['imagem_destaque'] ?? null;
    
    // Validação
    if (empty($nome_video) || empty($categorias_selecionadas)) {
        $mensagem = "⚠️ Nome do vídeo e pelo menos uma categoria são obrigatórios.";
        $tipo_mensagem = "error";
    } elseif (!isset($arquivo_previa) || $arquivo_previa['error'] != UPLOAD_ERR_OK) {
        $mensagem = "⚠️ A prévia do vídeo é obrigatória.";
        $tipo_mensagem = "error";
    } elseif (!isset($arquivo_imagem) || $arquivo_imagem['error'] != UPLOAD_ERR_OK) {
        $mensagem = "⚠️ A imagem de destaque é obrigatória.";
        $tipo_mensagem = "error";
    } else {
        $conexao->begin_transaction();
        
        try {
            // Processar prévia do vídeo
            $dir_previa = "uploads/videos/previas/";
            if (!is_dir($dir_previa)) mkdir($dir_previa, 0777, true);
            
            $ext_previa = pathinfo($arquivo_previa['name'], PATHINFO_EXTENSION);
            $nome_previa = uniqid("previa_") . "." . strtolower($ext_previa);
            $caminho_previa = $dir_previa . $nome_previa;
            
            $tipos_video_permitidos = ['video/mp4', 'video/webm', 'video/ogg'];
            if (!in_array($arquivo_previa['type'], $tipos_video_permitidos)) {
                throw new Exception("Formato de vídeo não permitido. Use MP4, WebM ou OGG.");
            }
            
            if ($arquivo_previa['size'] > 100 * 1024 * 1024) { // 100MB
                throw new Exception("A prévia é muito grande. Limite: 100MB.");
            }
            
            if (!move_uploaded_file($arquivo_previa['tmp_name'], $caminho_previa)) {
                throw new Exception("Erro ao fazer upload da prévia.");
            }
            
            // Processar imagem de destaque
            $dir_imagem = "uploads/videos/imagens/";
            if (!is_dir($dir_imagem)) mkdir($dir_imagem, 0777, true);
            
            $ext_imagem = pathinfo($arquivo_imagem['name'], PATHINFO_EXTENSION);
            $nome_imagem = uniqid("img_") . "." . strtolower($ext_imagem);
            $caminho_imagem = $dir_imagem . $nome_imagem;
            
            $tipos_imagem_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!in_array($arquivo_imagem['type'], $tipos_imagem_permitidos)) {
                throw new Exception("Formato de imagem não permitido. Use JPG, PNG ou WebP.");
            }
            
            if ($arquivo_imagem['size'] > 5 * 1024 * 1024) { // 5MB
                throw new Exception("A imagem é muito grande. Limite: 5MB.");
            }
            
            if (!move_uploaded_file($arquivo_imagem['tmp_name'], $caminho_imagem)) {
                throw new Exception("Erro ao fazer upload da imagem.");
            }
            
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
            
            $mensagem = "✅ Vídeo cadastrado com sucesso!";
            $tipo_mensagem = "success";
            $redirecionar = true;
            
        } catch (Exception $e) {
            $conexao->rollback();
            $mensagem = "❌ Erro: " . $e->getMessage();
            $tipo_mensagem = "error";
            
            // Limpar arquivos se houver erro
            if (isset($caminho_previa) && file_exists($caminho_previa)) unlink($caminho_previa);
            if (isset($caminho_imagem) && file_exists($caminho_imagem)) unlink($caminho_imagem);
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

            <form method="post" action="" enctype="multipart/form-data" class="form-container">
                
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

                <!-- Upload Prévia -->
                <div class="form-group">
                    <label>Prévia do Vídeo * (MP4, WebM ou OGG - Máx: 100MB)</label>
                    <input type="file" name="video_previa" id="video_previa" accept="video/*" class="file-input" required>
                    <div class="drop-zone" id="dropZonePrevia">
                        <div class="drop-zone-text">Arraste e solte a prévia aqui</div>
                        <button type="button" onclick="document.getElementById('video_previa').click()">
                            Ou Clique para Escolher
                        </button>
                        <p class="file-name" id="fileNamePrevia"></p>
                        <div class="preview-container" id="previewPrevia"></div>
                    </div>
                </div>

                <!-- Upload Imagem -->
                <div class="form-group">
                    <label>Imagem de Destaque * (JPG, PNG ou WebP - Máx: 5MB)</label>
                    <input type="file" name="imagem_destaque" id="imagem_destaque" accept="image/*" class="file-input" required>
                    <div class="drop-zone" id="dropZoneImagem">
                        <div class="drop-zone-text">Arraste e solte a imagem aqui</div>
                        <button type="button" onclick="document.getElementById('imagem_destaque').click()">
                            Ou Clique para Escolher
                        </button>
                        <p class="file-name" id="fileNameImagem"></p>
                        <div class="preview-container" id="previewImagem"></div>
                    </div>
                </div>

                <button type="submit">Cadastrar Vídeo</button>
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
        setupDropZone('dropZonePrevia', 'video_previa', 'fileNamePrevia', 'previewPrevia', 'video');
        
        // Drag and Drop para Imagem
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
            nameDisplay.textContent = `Arquivo: ${file.name}`;
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