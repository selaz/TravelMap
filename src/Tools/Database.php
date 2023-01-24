<?php

namespace Selaz\Tools;

use Monolog\Level;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use PDOException;

class Database {
    private static $instance = [];
    private mysqli $connect;
    private $error;
    /**
     * @var Logger
     */
    private $logger;

    private bool $queryDebug;

	private function __construct(int $pid) {
        $this->logger = Logger::getInstance('database');
        $this->conn($pid);
    }

    public function conn(int $pid) {
        $host = Env::getInstance()->getDbHost();
        $this->logger->debug(\sprintf('Try connect to %s@%s',$host,$host->getAttribute('database')));
        try {
            $this->queryDebug = $host->getAttribute('debug');
            $this->connect = new mysqli(
                $host->getHost(), 
                $host->getLogin(), 
                $host->getPassword(), 
                $host->getAttribute('database'),
                $host->getPort(),
                // sprintf('soc:%d',$pid)
            );
        } catch (PDOException $e) {
            $this->logger->exception(Level::Error,"Error while connect to ".$host,$e);
        }
    }
	
	/**
	 * @return Database
	 */
	public static function getInstance() {
        $pid = \posix_getpid();
		if (empty(self::$instance[$pid])) {
			self::$instance[$pid] = new self($pid);
		}
		
		return self::$instance[$pid];
	}

    private function prepareParam($param) {
        switch (\gettype($param)) {
            case "boolean":
                return ($param) ? 'true' : 'false';
            case "integer":
                return \intval($param);
            case "double":
                return \floatval($param);
            case "string":
                return sprintf("'%s'",$this->connect->escape_string($param));
            case "array":
            case "NULL":
            default:
                return 'NULL';
        }
    }

    public  function parse(string $sql,array $params): string {
        foreach ($params as $pName => $pVal) {
            $sql = \str_replace(sprintf(':%s',$pName),$this->prepareParam($pVal),$sql);
        }

        return $sql;
    }

    protected function query(string $sql, array $params = []): mysqli_result|bool {
        try {
            // $this->connect->ping();
            $sql = $this->parse($sql,$params);
            
            if ($this->queryDebug) { $this->logger->debug($sql); }

            $data = $this->connect->query($sql,\MYSQLI_STORE_RESULT);

            return $data;
        } catch (mysqli_sql_exception $e) {
            // $this->conn(\posix_getpid());
            // return $this->query($sql,$params);
            $this->logger->exception(Level::Error,$sql,$e);
            return false;
        }
    }

    public function getAll(string $sql,array $params = []): array {
        $result = $this->query($sql,$params);
        $data = ($result) ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $result->free();
        return $data;
    }

    public function getColumn(string $sql,array $params = []): array {
        $data = $this->getAll($sql,$params);
        $result = [];

        foreach ($data as $row) {
            $result[] = current($row);
        }

        return $result;
    }

    public function getRow(string $sql,array $params = []): array {
        $result = $this->query($sql,$params);
        $data = $result->fetch_assoc() ?? [];
        $result->free();
        return $data;
    }

    public function getOne(string $sql,array $params = []): int|string|null {
        return \current($this->getRow($sql,$params)) ?: null;
    }

    public function insert(string $sql,array $params = []): int|false {
        $result = $this->query($sql,$params);
        return ($result) ? $this->connect->insert_id : false;
    }

    public function update(string $sql,array $params = []): int {
        $result = $this->query($sql,$params);
        return ($result) ? $this->connect->affected_rows : 0;
    }

    public function getLastError(): string {
        return $this->connect->error;
    }
}