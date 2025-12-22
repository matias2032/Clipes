<?php
// Tenta pegar as variáveis do ambiente (Vercel Environment Variables)
// Se não existirem, usa os seus dados do Railway como fallback
$host = getenv('DB_HOST') ?: "mainline.proxy.rlwy.net";
$usuario = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') ?: "DAZICXZMMCgEqeCsXTaihqktrYxNgZJh";
$bd = getenv('DB_NAME') ?: "railway";
$port = getenv('DB_PORT') ?: "44870"; 

// Ordem correta dos parâmetros: Host, User, Pass, DB, Port
$conexao = new mysqli($host, $usuario, $password, $bd, $port);

if ($conexao->connect_error) {
    // Em produção, evite exibir o erro detalhado para o usuário final
    error_log("Erro de conexão: " . $conexao->connect_error);
    die("Desculpe, estamos com problemas técnicos.");
}

// --- CORREÇÃO DO ERRO FATAL DE GROUP BY ---
// Esta linha remove o modo restrito 'ONLY_FULL_GROUP_BY' da sessão atual.
// Isso resolve o erro "Expression #3 ... contains nonaggregated column" no seu projeto.
$conexao->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Opcional: define o charset para evitar problemas com acentos
$conexao->set_charset("utf8");

?>
