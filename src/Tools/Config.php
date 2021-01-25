<?php

namespace Selaz\Tools;

final class Config {

	/**
	 * @var \Selaz\Tools\Config 
	 */
	private static $_instance = [];
	private $configDir;
	
	private $data = [];

	/**
	 * @param string $env проверки все в setEnv
	 */
	private function __construct(string $name) {
		$this->configDir = sprintf( "%s/config", realpath( sprintf( "%s/../../", __DIR__ ) ) );
		
		$ex = pathinfo($name, PATHINFO_EXTENSION);
		
		switch ($ex) {
			case "ini":
				$this->loadConfigIni($name);
				break;
			case "yml":
			case "yaml":
				$this->loadConfigYaml($name);
				break;
			case "json":
				$this->loadConfigJson($name);
				break;
			default:
				throw new ErrorException('Unsupported config type');
		}
	}
	
	/**
	 * Основной лоад конфига (либо инициализирует, либо возвращает уже инициализированный)
	 * Можно прям сразу задать нужное окружение (для тестов, например, test)
	 * 
	 * Багофича: параметр окружения влияе только на инициализацию, возможно, есть смысл при передаче
	 * в инициализированный другого окружения его релоадить, но пока вроде не надо такого.
	 * @param string $env
	 * @return \Selaz\Tools\Config
	 */
	public static function load(string $name) {
		if (!isset(self::$_instance[$name])) {
			self::$_instance[$name] = new self($name);
		}
		return self::$_instance[$name];
	}

	/**
	 * Загружает произвольный файл основного конфига соответствующего окружения.
	 * 
	 * @param string $name - название конфига
	 * @return array
	 */
	protected function loadConfigIni( string $name ) {
		
		$file = sprintf("%s/%s",$this->configDir,$name);

		if ( is_file( $file ) && is_readable( $file ) ) {
			$this->data = parse_ini_file($file,true,INI_SCANNER_TYPED);
			if (is_array($this->data)) {
				$this->commaToArray($this->data);
			}
		} else {
			throw new \InvalidArgumentException(sprintf('Can`t read config file %s',$file));
		}
	}
	
	protected function loadConfigYaml( string $name ) {
		
		$file = sprintf("%s/%s",$this->configDir,$name);

		if ( is_file( $file ) && is_readable( $file ) ) {
			$this->data = yaml_parse_file($file);
		} else {
			throw new \InvalidArgumentException(sprintf('Can`t read config file %s',$file));
		}
	}
	
	protected function loadConfigJson( string $name ) {
		
		$file = sprintf("%s/%s",$this->configDir,$name);

		if ( is_file( $file ) && is_readable( $file ) ) {
			$this->data = json_decode($file,true);
		} else {
			throw new \InvalidArgumentException(sprintf('Can`t read config file %s',$file));
		}
	}
	
	protected function commaToArray( array &$data ) {
		foreach ( $data as &$v ) {
			if (is_array($v)) {
				$this->commaToArray($v);
			} else {
				if (strpos($v, ',') !== false) {
					$v = explode(',', $v);
				}
			}
		}
	}

	public function get(string $name, string $block = 'main') {
		return $this->data[$block][$name] ?? null;
	}

	public function getBlock(string $name) {
		return $this->data[$name] ?? null;
	}
	
	public function getAll() {
		return $this->data;
	}

	public static function write_ini_file($name, $array = []) {
		$file = sprintf("%s/%s",sprintf( "%s/config", realpath( sprintf( "%s/../../", __DIR__ ) ) ),$name);
        // check first argument is string
        if (!is_string($file)) {
            throw new \InvalidArgumentException('Function argument 1 must be a string.');
        }

        // check second argument is array
        if (!is_array($array)) {
            throw new \InvalidArgumentException('Function argument 2 must be an array.');
        }

        // process array
        $data = array();
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $data[] = "[$key]";
                foreach ($val as $skey => $sval) {
                    if (is_array($sval)) {
                        foreach ($sval as $_skey => $_sval) {
                            if (is_numeric($_skey)) {
                                $data[] = $skey.'[] = '.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
                            } else {
                                $data[] = $skey.'['.$_skey.'] = '.(is_numeric($_sval) ? $_sval : (ctype_upper($_sval) ? $_sval : '"'.$_sval.'"'));
                            }
                        }
                    } else {
                        $data[] = $skey.' = '.(is_numeric($sval) ? $sval : (ctype_upper($sval) ? $sval : '"'.$sval.'"'));
                    }
                }
            } else {
                $data[] = $key.' = '.(is_numeric($val) ? $val : (ctype_upper($val) ? $val : '"'.$val.'"'));
            }
            // empty line
            $data[] = null;
        }

        // open file pointer, init flock options
        $fp = fopen($file, 'w');
        $retries = 0;
        $max_retries = 100;

        if (!$fp) {
            return false;
        }

        // loop until get lock, or reach max retries
        do {
            if ($retries > 0) {
                usleep(rand(1, 5000));
            }
            $retries += 1;
        } while (!flock($fp, LOCK_EX) && $retries <= $max_retries);

        // couldn't get the lock
        if ($retries == $max_retries) {
            return false;
        }

        // got lock, write data
        fwrite($fp, implode(PHP_EOL, $data).PHP_EOL);

        // release lock
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

}