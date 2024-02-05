<?php

require_once 'vendor/autoload.php'; // Include the Composer autoloader
use Dotenv\Dotenv;

class Database {
    protected $pdo;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        $host = $_ENV['DB_HOST'];
        $dbname = $_ENV['DB_NAME'];
        $username = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASSWORD'];

        try {
            $this->pdo = new PDO("mysql:host={$host};dbname={$dbname}", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
            die();
        }
    }

    /**
     * Executes an SQL query with optional parameters and returns the result.
     * 
     * @param string $sql The SQL query to execute.
     * @param array $params Optional parameters to bind to the query.
     * @param bool $fetchAll Determines if all rows should be returned (true) or just one (false).
     * @return mixed The result set or status of the operation.
     */
    public function executeQuery($sql, $params = [], $fetchAll = true) {
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }

        $stmt->execute();

        if (strpos(strtoupper($sql), 'SELECT') !== false) {
            return $fetchAll ? $stmt->fetchAll(PDO::FETCH_ASSOC) : $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            return $stmt->rowCount(); // For INSERT, UPDATE, DELETE
        }
    }
    public function beginTransaction() {
        $this->pdo->beginTransaction();
    }
    
    public function commit() {
        $this->pdo->commit();
    }
    
    public function rollBack() {
        $this->pdo->rollBack();
    }
    public function lastInsertId() {
        $this->pdo->lastInsertId();
    }
}
