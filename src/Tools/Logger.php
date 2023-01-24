<?php

namespace Selaz\Tools;

use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Selaz\Tools\Env;
use Throwable;

class Logger extends MonologLogger {

    private static $instance = [];

	private function __construct(string $channel = null) {
		parent::__construct($channel ?? 'default');
		$this->setHandlers(Env::getInstance()->getLogHandlers());
	}
	
	/**
	 * 
	 * @param string $channel
	 * @return Logger
	 */
	public static function getInstance(string $channel = null) {
		if (empty(self::$instance[$channel])) {
			self::$instance[$channel] = new self($channel);
		}
		
		return self::$instance[$channel];
	}

	public function exception(Level $level, string $message, Throwable $e, array $context = []) {
		$this->addRecord($level,$message,array_merge($context,['pid'=>\posix_getpid()]));
		$this->addRecord($level,sprintf('Exception: %s',$e::class));
		$this->addRecord($level,$e->getMessage(),[$e->getCode()]);
		$this->addRecord($level,$e->getTraceAsString());
	}
}