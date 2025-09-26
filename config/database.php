<?php
// Arquivo de Configuração do Banco de Dados - Salvar como config/database.php

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'encaminhamais_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Classe para conexão com o banco de dados
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Erro na conexão com o banco de dados: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Método para executar queries SELECT
    public function select($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro na consulta SELECT: " . $e->getMessage());
            return false;
        }
    }
    
    // Método para executar queries INSERT, UPDATE, DELETE
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erro na execução da query: " . $e->getMessage());
            return false;
        }
    }
    
    // Método para obter o último ID inserido
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    // Método para contar registros
    public function count($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Erro na contagem: " . $e->getMessage());
            return 0;
        }
    }
}

// Função helper para obter a instância do banco
function getDB() {
    return Database::getInstance();
}
?>
