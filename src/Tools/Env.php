<?php 

namespace Selaz\Tools;

use Monolog\Handler\StreamHandler;
use Psr\Log\LogLevel;

use Selaz\Entities\Host;

class Env {

    private static $instance;

	public static function getInstance(): Env {
		if (empty(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

    public function getMemcacheHost() {
        return $this->loadHostConfig('MEMCACHE');
    }

    public function getDbHost() {
        $host = $this->loadHostConfig('DB_HOST');

        return $host;
    }

    public function getLogHandlers(): array {
        $handler = new StreamHandler(\getenv('DEBUG_LOG'),LogLevel::DEBUG);

        $handler->setFormatter(new ColoredLineFormatter(
            "%color_start%[%datetime%] %channel%.%level_name%: %message% %context%%color_end%\n",
            'Y-m-d H:i:s',
            true,
            true
        ));
        return [$handler];
    }

    public function getResourcePath() {
        $path = \realpath(__DIR__ . '/../../resources');
        return $path;
    }

    protected function loadHostConfig(string $name): Host {
        $config = \getenv($name);
        if (empty($config)) {
            throw new \InvalidArgumentException(sprintf('%s is not defined in env',$name));
        }

        $cfg = parse_url($config);

        $host = new Host(
            $cfg['host'],
            $cfg['port'],
            $cfg['scheme'] ?? null,
            $cfg['user'] ?? null,
            $cfg['pass'] ?? null,
            $cfg['path'] ?? null
        );

        if (!empty($cfg['query'])) {
            $attrs = [];
            \parse_str($cfg['query'],$attrs);
            foreach ($attrs as $name => $val) {
                $host->setAttribute($name,$val);
            }
        }

        return $host;
    }
}