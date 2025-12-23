<?php
// sessao_handler_db.php - Handler de sessão usando MySQL
// Coloque este arquivo em: clipes/api/sessao_handler_db.php

class SessionHandlerDB implements SessionHandlerInterface {
    private $conexao;
    
    public function __construct($conexao) {
        $this->conexao = $conexao;
    }
    
    public function open($path, $name): bool {
        return true;
    }
    
    public function close(): bool {
        return true;
    }
    
    public function read($id): string|false {
        $stmt = $this->conexao->prepare(
            "SELECT dados FROM sessoes WHERE id = ? AND expira > NOW()"
        );
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['dados'];
        }
        return '';
    }
    
    public function write($id, $data): bool {
        $expira = date('Y-m-d H:i:s', time() + 86400); // 24h
        $stmt = $this->conexao->prepare(
            "REPLACE INTO sessoes (id, dados, expira) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $id, $data, $expira);
        return $stmt->execute();
    }
    
    public function destroy($id): bool {
        $stmt = $this->conexao->prepare("DELETE FROM sessoes WHERE id = ?");
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }
    
    public function gc($max_lifetime): int|false {
        $stmt = $this->conexao->prepare("DELETE FROM sessoes WHERE expira < NOW()");
        $stmt->execute();
        return $stmt->affected_rows;
    }
}

?>
