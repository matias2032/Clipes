<?php
/**
 * excluir_videos.php
 * EXCLUSÃO SEGURA COM REMOÇÃO DO VERCEL BLOB
 */

include "verifica_login.php";
include "conexao.php";
include "vercel_blob_upload.php";

header("Content-Type: text/html; charset=UTF-8");

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['idperfil'] != 1) {
    header("Location: login.php");
    exit;
}

$mensagem = "";
$tipo_mensagem = "error";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['videos_ids'])) {
    $ids = array_map('intval', $_POST['videos_ids']);
    
    if (empty($ids)) {
        header("Location: gerenciar_videos.php");
        exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    try {
        $conexao->begin_transaction();
        
        // 1. BUSCAR TODOS OS ARQUIVOS ANTES DE EXCLUIR
        $stmt = $conexao->prepare("
            SELECT v.id_video, v.caminho_previa, vi.caminho_imagem 
            FROM video v
            LEFT JOIN video_imagem vi ON v.id_video = vi.id_video AND vi.imagem_principal = 1
            WHERE v.id_video IN ($placeholders)
        ");
        
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $arquivos_para_deletar = [];
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['caminho_previa'])) {
                $arquivos_para_deletar[] = $row['caminho_previa'];
            }
            if (!empty($row['caminho_imagem'])) {
                $arquivos_para_deletar[] = $row['caminho_imagem'];
            }
        }
        
        // 2. EXCLUIR DO BANCO DE DADOS (CASCADE vai cuidar das tabelas relacionadas)
        $stmt_delete = $conexao->prepare("DELETE FROM video WHERE id_video IN ($placeholders)");
        $stmt_delete->bind_param($types, ...$ids);
        $stmt_delete->execute();
        
        $videos_excluidos = $stmt_delete->affected_rows;
        
        $conexao->commit();
        
        // 3. REMOVER ARQUIVOS DO VERCEL BLOB (após commit bem-sucedido)
        $arquivos_removidos = 0;
        $erros_remocao = [];
        
        if (!empty($arquivos_para_deletar) && isVercelBlobConfigured()) {
            foreach ($arquivos_para_deletar as $url) {
                try {
                    deleteFromVercelBlob($url);
                    $arquivos_removidos++;
                } catch (Exception $e) {
                    $erros_remocao[] = basename($url) . ": " . $e->getMessage();
                    error_log("Erro ao remover do Vercel Blob: " . $url . " - " . $e->getMessage());
                }
            }
        }
        
        // 4. MENSAGEM DE SUCESSO
        if ($videos_excluidos > 0) {
            $mensagem = "✅ {$videos_excluidos} vídeo(s) excluído(s) com sucesso!<br>";
            
            if ($arquivos_removidos > 0) {
                $mensagem .= "🗑️ {$arquivos_removidos} arquivo(s) removido(s) do Vercel Blob.";
            }
            
            if (!empty($erros_remocao)) {
                $mensagem .= "<br>⚠️ Alguns arquivos não puderam ser removidos do Vercel Blob:<br>" . 
                             implode("<br>", $erros_remocao);
            }
            
            $tipo_mensagem = "success";
        } else {
            $mensagem = "⚠️ Nenhum vídeo foi excluído.";
        }
        
    } catch (Exception $e) {
        $conexao->rollback();
        $mensagem = "❌ Erro ao excluir vídeos: " . $e->getMessage();
        $tipo_mensagem = "error";
        error_log("Erro na exclusão de vídeos: " . $e->getMessage());
    }
    
} else {
    header("Location: gerenciar_videos.php");
    exit;
}

// Limpar buffer
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exclusão de Vídeos</title>
<link rel="stylesheet" href="../css/admin.css">
<style>
.mensagem-container {
    max-width: 600px;
    margin: 100px auto;
    padding: 30px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    text-align: center;
}

.mensagem {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    line-height: 1.6;
}

.mensagem.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.mensagem.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.btn-voltar {
    display: inline-block;
    padding: 12px 30px;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: bold;
    transition: background 0.2s;
}

.btn-voltar:hover {
    background: #2980b9;
}
</style>
</head>
<body>

<div class="mensagem-container">
    <div class="mensagem <?= $tipo_mensagem ?>">
        <?= $mensagem ?>
    </div>
    
    <a href="gerenciar_videos.php" class="btn-voltar">⬅️ Voltar para Gerenciar Vídeos</a>
</div>

<script>
// Redirecionar automaticamente após 5 segundos
setTimeout(function() {
    window.location.href = 'gerenciar_videos.php';
}, 5000);
</script>

</body>
</html>