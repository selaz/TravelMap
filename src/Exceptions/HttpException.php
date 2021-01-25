<?php

namespace Selaz\Exceptions;

class HttpException extends \Exception {
	const BAD_REQUEST = 400;
	const UNAUTHORIZED = 401;
	const FORBIDDEN = 403;
	const NOT_FOUND = 404;
	const METHOD_NOT_ALLOWED = 405;
	const WRONG_PARAMETERS = 422;
	const INTERNAL_ERROR = 500;
	
	private $headers = [];
	
	/**
	 * Http Ядро перехватит исключение, и вернёт его пользователю. Код исключения = http коду (из списка поддерживаемых)
	 * Message будет показан пользователю
	 * Допустимый список http кодов:
	 * 
	 * 	400,	#Запрос невалидный.
	 * 	401,	#Ошибка авторизации.
	 * 	403,	#Превышено ограничение на доступ к ресурсам. | У вас нет доступа к этому ресурсу.
	 * 	404,	#Указанный в запросе объект не найден.
	 * 	405,	#Используемый метод не поддерживается.
	 * 	422		#В запросе отсутствует обязательный параметр. | Значение параметра, указанного в запросе, не соответствует формату. 
	 *  500		#Внутренняя ошибка сервера.
	 * 
	 * @param string $message - отображаемое пользователю сообщение
	 * @param int $code - http код
	 * @param Throwable $previous
	 */
	public function __construct( $message = "", $code = 0, array $headers = [] ) {
		$this->setHeaders($headers);
		parent::__construct( $message, $code );
	}
	
	/**
	 * Добавляет заголовки к http ответу
	 * @param array $headers
	 */
	public function setHeaders( array $headers ) {
		$this->headers = $headers;
	}

	/**
	 * Получить дополнительные заголовки к ответу
	 * @return array
	 */
	public function getHeaders() {
		return $this->headers;
	}
}