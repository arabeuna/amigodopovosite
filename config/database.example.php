<?php
// Configurações do banco de dados - EXEMPLO
// Copie este arquivo para database.php e configure com seus dados reais

// Configurações de conexão
define('DB_HOST', 'localhost');
define('DB_NAME', 'nome_do_seu_banco');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    private $pdo;
    
    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                error_log("Erro de conexão com o banco: " . $e->getMessage());
                throw new Exception("Erro de conexão com o banco de dados");
            }
        }
        
        return $this->pdo;
    }
    
    public function disconnect() {
        $this->pdo = null;
    }
    
    public function getConnection() {
        return $this->connect();
    }
    
    // Método para testar a conexão
    public function testConnection() {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Método para executar queries preparadas
    public function executeQuery($sql, $params = []) {
        try {
            $pdo = $this->connect();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erro na query: " . $e->getMessage());
            throw new Exception("Erro na execução da query");
        }
    }
}
?>