<?php
// index.php
include "conexao.php";
include "verifica_login_opcional.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$usuarioLogado = $_SESSION['usuario'] ?? null;
$id_perfil = $usuarioLogado['idperfil'] ?? null;
$idUsuario = $usuarioLogado['id_usuario'] ?? null;

// YOUR TELEGRAM CONTACT - CONFIGURE HERE
$TELEGRAM_CONTACT = "https://t.me/peterparker1232"; // Change to your Telegram username

// Load categories for filter
$categorias = $conexao->query("SELECT id_categoria, nome_categoria FROM categoria ORDER BY nome_categoria");

$filtros = [];
$tipos_bind = "";

// Base query
$sql_base = "FROM video v
LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = 1
WHERE v.ativo = 1";

// Filters
if (!empty($_GET['categoria'])) {
    $sql_base .= " AND EXISTS (SELECT 1 FROM video_categoria vc WHERE vc.id_video = v.id_video AND vc.id_categoria = ?)";
    $tipos_bind .= "i";
    $filtros[] = $_GET['categoria'];
}

if (!empty($_GET['busca'])) {
    $busca = "%" . trim($_GET['busca']) . "%";
    $sql_base .= " AND (v.nome_video LIKE ? OR v.descricao LIKE ?)";
    $tipos_bind .= "ss";
    $filtros[] = $busca;
    $filtros[] = $busca;
}

// Minimum duration filter
if (!empty($_GET['duracao_min'])) {
    $sql_base .= " AND TIME_TO_SEC(v.duracao) >= ?";
    $tipos_bind .= "i";
    $filtros[] = intval($_GET['duracao_min']) * 60;
}

// Maximum duration filter
if (!empty($_GET['duracao_max'])) {
    $sql_base .= " AND TIME_TO_SEC(v.duracao) <= ?";
    $tipos_bind .= "i";
    $filtros[] = intval($_GET['duracao_max']) * 60;
}

// Minimum price filter
if (!empty($_GET['preco_min'])) {
    $sql_base .= " AND v.preco >= ?";
    $tipos_bind .= "d";
    $filtros[] = floatval($_GET['preco_min']);
}

// Maximum price filter
if (!empty($_GET['preco_max'])) {
    $sql_base .= " AND v.preco <= ?";
    $tipos_bind .= "d";
    $filtros[] = floatval($_GET['preco_max']);
}

// Pagination
$limite = 12;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $limite;

// Count total
$sql_count = "SELECT COUNT(*) AS total " . $sql_base;
$stmt_count = $conexao->prepare($sql_count);
if (!empty($filtros)) {
    $bind_args = array_merge([$tipos_bind], $filtros);
    call_user_func_array([$stmt_count, 'bind_param'], array_by_ref($bind_args));
}
$stmt_count->execute();
$total_registros = $stmt_count->get_result()->fetch_assoc()['total'];
$total_paginas = ceil($total_registros / $limite);

// Main query
$sql = "SELECT v.*, vi.caminho_imagem
" . $sql_base . " ORDER BY v.data_cadastro DESC LIMIT ? OFFSET ?";

$stmt = $conexao->prepare($sql);
$tipos_completo = $tipos_bind . "ii";
$parametros = array_merge($filtros, [$limite, $offset]);
if (!empty($parametros)) {
    $bind_args = array_merge([$tipos_completo], $parametros);
    call_user_func_array([$stmt, 'bind_param'], array_by_ref($bind_args));
}

function array_by_ref(&$arr) {
    $refs = [];
    foreach ($arr as $key => $value) $refs[$key] = &$arr[$key];
    return $refs;
}

$stmt->execute();
$resultado = $stmt->get_result();

// AJAX - Register view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_visualizacao'])) {
    header('Content-Type: application/json');
    
    $idVideo = intval($_POST['id_video']);
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if ($idUsuario) {
        $stmt = $conexao->prepare("SELECT id_download FROM video_download_previa 
                                   WHERE id_video = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $idVideo, $idUsuario);
    } else {
        $stmt = $conexao->prepare("SELECT id_download FROM video_download_previa 
                                   WHERE id_video = ? AND ip_address = ? AND id_usuario IS NULL");
        $stmt->bind_param("is", $idVideo, $ip);
    }
    
    $stmt->execute();
    $resultado_check = $stmt->get_result();
    
    if ($resultado_check->num_rows == 0) {
        $stmtInsert = $conexao->prepare("INSERT INTO video_download_previa (id_video, id_usuario, ip_address) 
                                         VALUES (?, ?, ?)");
        $stmtInsert->bind_param("iis", $idVideo, $idUsuario, $ip);
        $stmtInsert->execute();
        
        $stmtUpdate = $conexao->prepare("UPDATE video SET visualizacoes = visualizacoes + 1 WHERE id_video = ?");
        $stmtUpdate->bind_param("i", $idVideo);
        $stmtUpdate->execute();
        
        $stmtCount = $conexao->prepare("SELECT visualizacoes FROM video WHERE id_video = ?");
        $stmtCount->bind_param("i", $idVideo);
        $stmtCount->execute();
        $novoTotal = $stmtCount->get_result()->fetch_assoc()['visualizacoes'];
        
        echo json_encode(['success' => true, 'visualizacoes' => $novoTotal]);
    } else {
        $stmtCount = $conexao->prepare("SELECT visualizacoes FROM video WHERE id_video = ?");
        $stmtCount->bind_param("i", $idVideo);
        $stmtCount->execute();
        $total = $stmtCount->get_result()->fetch_assoc()['visualizacoes'];
        
        echo json_encode(['success' => true, 'already_viewed' => true, 'visualizacoes' => $total]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Video Repository</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="css/basico.css">
<script src="js/dropdown.js"></script>
<script src="js/paginacao.js"></script>
<script src="js/darkmode1.js"></script>
<script src="js/sidebar2.js"></script>
</head>
<body>

<!-- Topbar -->
<?php
$nome2 = $usuarioLogado['nome'] ?? '';
$apelido = $usuarioLogado['apelido'] ?? '';
$iniciais = ($nome2 && $apelido) ? strtoupper(substr($nome2, 0, 1) . substr($apelido, 0, 1)) : '';
$nomeCompleto = "$nome2 $apelido";

if (!function_exists('gerarCor')) {
    function gerarCor($texto) {
        $hash = md5($texto);
        $r = hexdec(substr($hash, 0, 2));
        $g = hexdec(substr($hash, 2, 2));
        $b = hexdec(substr($hash, 4, 2));
        return "rgb($r, $g, $b)";
    }
}
$corAvatar = gerarCor($nomeCompleto);
?>

<header class="topbar">
    <div class="container">
        
        <button class="menu-btn-mobile" id="menuBtnMobile">&#9776;</button>

        <div class="logo">
            <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 10px;">
                <img src="icones/logo.png" alt="Logo" class="logo-img" style="height: 40px;">
                <span class="logo-text">VideoHub</span>
            </a>
        </div>

        <div class="nav-links desktop-only">
            <a href="index.php">
                <img class="icone2" src="icones/casa1.png" alt="Home"> Home
            </a>
            <a href="index.php">
                <img class="icone2" src="icones/video.png" alt="Videos"> Videos
            </a>
        </div>

        <div class="acoes-usuario">
            <img class="dark-toggle" id="darkToggle" src="icones/lua.png" alt="Dark Mode" title="Toggle theme">

            <?php if ($usuarioLogado): ?>
                <div class="usuario-info usuario-desktop" id="usuarioDropdown">
                    <div class="usuario-dropdown">
                        <div class="usuario-iniciais" style="background-color: <?= $corAvatar ?>;">
                            <?= $iniciais ?>
                        </div>
                        <div class="usuario-info-texto desktop-only">
                            <div class="usuario-nome"><?= $nomeCompleto ?></div>
                        </div>

                        <div class="menu-perfil" id="menuPerfil">
                            <a href="alterar_senha2.php">
                                <img class="icone" src="icones/cadeado1.png"> Change Password
                            </a>
                            <a href="logout.php" style="color: #d32f2f;">
                                <img class="iconelogout" src="icones/logout1.png"> Logout
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn-login">
                    <img class="icone2" src="icones/login1.png"> Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<nav id="mobileMenu" class="nav-mobile-sidebar hidden">
    <div class="sidebar-header">
        <span class="logo-text">Menu</span>
        <button class="close-btn" id="closeMobileMenu">&times;</button>
    </div>

    <ul class="sidebar-links">
        <li><a href="index.php"><img class="icone2" src="icones/casa1.png"> Home</a></li>
        <li><a href="index.php"><img class="icone2" src="icones/video.png"> Videos</a></li>
    </ul>
</nav>
<div class="menu-overlay hidden" id="menuOverlay"></div>

<!-- Main Container -->
<div class="main-container">
    <!-- Toggle Filters Button -->
    <button class="filter-toggle-btn" id="filterToggle">
        <i class="fas fa-filter"></i>
        <span>Show Filters</span>
        <i class="fas fa-chevron-down"></i>
    </button>

    <!-- Filters -->
    <div class="filters" id="filtersContainer">
        <h2><i class="fas fa-filter"></i> Search Filters</h2>
        <form method="get">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Search Video</label>
                    <input type="text" name="busca" placeholder="Video name..." 
                           value="<?= htmlspecialchars($_GET['busca'] ?? '') ?>">
                </div>
                
                <div class="filter-group">
                    <label>Category</label>
                    <select name="categoria">
                        <option value="">All</option>
                        <?php $categorias->data_seek(0); while ($cat = $categorias->fetch_assoc()): ?>
                            <option value="<?= $cat['id_categoria'] ?>" 
                                    <?= isset($_GET['categoria']) && $_GET['categoria'] == $cat['id_categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nome_categoria']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Min Duration (min)</label>
                    <input type="number" name="duracao_min" placeholder="Ex: 5" min="0"
                           value="<?= htmlspecialchars($_GET['duracao_min'] ?? '') ?>">
                </div>
                
                <div class="filter-group">
                    <label>Max Duration (min)</label>
                    <input type="number" name="duracao_max" placeholder="Ex: 60" min="0"
                           value="<?= htmlspecialchars($_GET['duracao_max'] ?? '') ?>">
                </div>
                
                <div class="filter-group">
                    <label>Min Price ($)</label>
                    <input type="number" name="preco_min" placeholder="Ex: 10" min="0" step="0.01"
                           value="<?= htmlspecialchars($_GET['preco_min'] ?? '') ?>">
                </div>
                
                <div class="filter-group">
                    <label>Max Price ($)</label>
                    <input type="number" name="preco_max" placeholder="Ex: 100" min="0" step="0.01"
                           value="<?= htmlspecialchars($_GET['preco_max'] ?? '') ?>">
                </div>
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="btn-filter btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <button type="button" onclick="window.location='index.php'" class="btn-filter btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </form>
    </div>

    <!-- Count -->
    <div class="count">
        <i class="fas fa-video"></i> <?= $resultado->num_rows ?> video(s) found
    </div>

    <!-- Video Grid -->
    <div class="videos-grid">
        <?php while ($v = $resultado->fetch_assoc()): ?>
            <div class="video-card">
                <div class="video-thumbnail-wrapper">
                    <?php if ($v['caminho_imagem'] && file_exists($v['caminho_imagem'])): ?>
                        <img src="<?= $v['caminho_imagem'] ?>" alt="Thumbnail" class="video-thumbnail">
                    <?php else: ?>
                        <div class="video-thumbnail" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                    <?php endif; ?>
                    
                    <div class="price-badge">$<?= number_format($v['preco'], 2) ?></div>
                    <?php if ($v['duracao']): ?>
                        <div class="duration-badge">
                            <i class="far fa-clock"></i> <?= $v['duracao'] ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="video-info">
                    <h3 class="video-title"><?= htmlspecialchars($v['nome_video']) ?></h3>
                    
                    <div class="video-stats">
                        <span><i class="fas fa-eye"></i> <?= number_format($v['visualizacoes']) ?></span>
                        <span class="online-badge"><i class="fas fa-circle"></i> Online</span>
                    </div>
                    
                    <div class="action-buttons">
                        <button onclick="abrirPreview(<?= $v['id_video'] ?>, '<?= addslashes($v['caminho_previa']) ?>')" 
                                class="action-btn btn-preview">
                            <i class="far fa-play-circle"></i> Preview
                        </button>
                        
                        <button onclick="sendTelegramMessage(<?= $v['id_video'] ?>, '<?= addslashes($v['nome_video']) ?>', <?= $v['preco'] ?>, '<?= $v['duracao'] ?>')" 
                                class="action-btn btn-telegram">
                            <i class="fab fa-telegram"></i> Send Message
                        </button>
                        
                        <button onclick="sendTelegramMessage(<?= $v['id_video'] ?>, '<?= addslashes($v['nome_video']) ?>', <?= $v['preco'] ?>, '<?= $v['duracao'] ?>')" 
                                class="action-btn btn-pay">
                            <i class="fas fa-credit-card"></i> Buy Now
                        </button>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_paginas > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_paginas; $i++): 
                $params = $_GET;
                $params['pagina'] = $i;
                $url = '?' . http_build_query($params);
            ?>
                <a href="<?= $url ?>" class="<?= $i == $pagina_atual ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Preview Modal -->
<div id="modalPreview" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="fecharPreview()">&times;</span>
        <video id="videoPreview" class="video-player" controls>
            <source id="videoSource" src="" type="video/mp4">
        </video>
    </div>
</div>

<script>
// Toggle Filters
const filterToggle = document.getElementById('filterToggle');
const filtersContainer = document.getElementById('filtersContainer');

filterToggle.addEventListener('click', function() {
    filtersContainer.classList.toggle('show');
    filterToggle.classList.toggle('active');
    
    const span = filterToggle.querySelector('span');
    if (filtersContainer.classList.contains('show')) {
        span.textContent = 'Hide Filters';
    } else {
        span.textContent = 'Show Filters';
    }
});

// Send Telegram Message with Video Info
function sendTelegramMessage(videoId, videoName, price, duration) {
    const currentUrl = window.location.href;
    const videoUrl = `${window.location.origin}${window.location.pathname}?video=${videoId}`;
    
    // Create message template
    const message = `Hello! I'm interested in purchasing this video:

📹 Video Title: ${videoName}
💰 Price: $${price.toFixed(2)}
⏱️ Duration: ${duration}
🔗 Link: ${videoUrl}

Please provide me with information on how to proceed with the purchase.`;
    
    // Encode message for URL
    const encodedMessage = encodeURIComponent(message);
    
    // Telegram URL with pre-filled message
    const telegramUrl = `<?= $TELEGRAM_CONTACT ?>?text=${encodedMessage}`;
    
    // Open Telegram
    window.open(telegramUrl, '_blank');
}

// Preview Functions
function abrirPreview(idVideo, caminho) {
    document.getElementById('modalPreview').style.display = 'block';
    document.getElementById('videoSource').src = caminho;
    document.getElementById('videoPreview').load();
    document.body.style.overflow = 'hidden';
    
    // Register view
    const formData = new FormData();
    formData.append('registrar_visualizacao', '1');
    formData.append('id_video', idVideo);
    
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.json())
        .catch(err => console.error('Error registering view:', err));
}

function fecharPreview() {
    document.getElementById('modalPreview').style.display = 'none';
    const player = document.getElementById('videoPreview');
    player.pause();
    player.currentTime = 0;
    document.body.style.overflow = 'auto';
}

// Close on outside click
window.onclick = function(event) {
    const modal = document.getElementById('modalPreview');
    if (event.target == modal) {
        fecharPreview();
    }
}

// Close with ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        fecharPreview();
    }
});
</script>

</body>
</html>