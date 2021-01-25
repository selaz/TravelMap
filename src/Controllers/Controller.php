<?php

/**
 * Общий контроллер для всех контроллеров всех проектов.
 * Содержит всё самое необходимое для организации проектных контроллеров, от которых уже скорее всего
 * будут наследоваться конкретные контроллеры страниц проекта (хотя можно и от этого, конечно).
 */

namespace Selaz\Controllers;

use Symfony\Component\HttpFoundation\Request,
	Symfony\Component\HttpFoundation\Response,
	Symfony\Component\HttpFoundation\ResponseHeaderBag;

class Controller {

	private $template;
	private $templateData = [];
	private $answerType = 'html';
	private $json;
	private $statusCode = 200;
	private $action;
	private $headers = [];

	/**
	 * Объект запроса.
	 * @var \Symfony\Component\HttpFoundation\Request 
	 */
	private $request;
	
	/**
	 *
	 * @var \Symfony\Component\HttpFoundation\ResponseHeaderBag
	 */
	private $responeHeaders;
	
	/**
	 * Параметры запроса, полученные из роутера.
	 * @var array
	 */
	private $params = array();

	/**
	 * Сетим объект запроса
	 * @param Request $request
	 */
	final public function setRequest( Request $request ) {
		$this->request = $request;
	}

	/**
	 * Получение объекта запроса.
	 * @return Request
	 */
	final public function getRequest() {
		return $this->request;
	}
	
	/**
	 * Заголовки ответа, нужны как минимум для кук (есть метод setCookie внутри)
	 * @param ResponseHeaderBag $headers
	 */
	final public function setResponseHeaders( ResponseHeaderBag $headers ) {
		$this->responeHeaders = $headers;
	}
	
	final public function getResponseHeaders() {
		return $this->responeHeaders;
	}

	/**
	 * Сетим параметры запроса из роутера.
	 * @param array $params
	 */
	final public function setParams( array $params ) {
		$this->params = $params;
	}
	
	/**
	 * Добавляет дополнительный заголовок в ответ сервера
	 * 
	 * @param string $name
	 * @param string $value
	 */
	final public function addHeader( string $name, string $value ) {
		$this->headers[$name] = $value;
	}
	
	/**
	 * Сбрасывает устанновленный до этого массив заголовков ответа
	 */
	final public function flushHeaders() {
		$this->headers = [];
	}
	
	/**
	 * Возвращает дополнительные заголовки, которые будут добавленны в ответ
	 * 
	 * @return array
	 */
	final public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Получение полного списка параметров запроса.
	 * @return array
	 */
	final public function getParams() {
		return $this->params;
	}

	/**
	 * Получение параметра запроса по названию.
	 * @param string $name Название параметра.
	 * @param mixed $default [optional] Дефолтное значение, если параметр не задан. По умолчанию, NULL.
	 * @return mixed
	 */
	final public function getParam( string $name, $default = NULL ) {
		if ( isset( $this->params[ $name ] ) ) {
			return $this->params[ $name ];
		}

		return $default;
	}

	/**
	 * Метод, который предполагается делать непосредственно перед вызовом основного экшна.
	 */
	public function preAction() {
	}

	/**
	 * Метод, который предполагается делать сразу после вызова основного экшна.
	 */
	public function postAction() {
	}

	/**
	 * Метод вызывается из индекса в самом конце, после ответа клиента (и после fastcgi_finish_request).
	 */
	public function finish( Response $response ) {
		
	}

	/**
	 * Просто для удобства. Добавляет переменную title в twig
	 * 
	 * @param string $title
	 */
	protected function setTitle( string $title ) {
		$this->setTemplateData( 'title', $title );
	}

	/**
	 * Возвращает текущий установленный шаблон в который мы собираемся рендерится после выполнения контроллера
	 * @return string
	 */
	public function getTemplate() {
		return $this->template;
	}

	/**
	 * Устанавливаем шаблон в который мы собираемся рендерится после выполнения контроллера
	 * @param string $tpl
	 */
	protected function setTemplate( string $tpl ) {
		$this->template = $tpl;
	}

	/**
	 * Переменные, которые уйдут в twig
	 * 
	 * @return array
	 */
	public function getTemplateData() {
		return $this->templateData;
	}
	
	/**
	 * Получаем переменную для твига по ключу
	 * Бывает периодически удобно проверить что мы ранее засетили, например
	 * Вернет NULL, если ранее не засетили (ну или засетили NULL)
	 * 
	 * @param string $key
	 * @return type|NULL
	 */
	public function getTemplateDataItem( string $key ) {
		return ( $this->templateData[$key] ?? NULL );
	}

	/**
	 * Устанавливаем переменные twig массивом (т.е. сразу много переменных)
	 * @param array $data
	 */
	protected function setTemplateDataArr( array $data ) {
		$this->templateData = array_merge( $this->templateData, $data );
	}

	/**
	 * Устанавливаем переменную twig
	 * 
	 * @param string $key
	 * @param mixed $data
	 */
	protected function setTemplateData( string $key, $data ) {
		$this->templateData = array_merge( $this->templateData, [ $key => $data ] );
	}

	/**
	 * Удаляем переменную из twig
	 * @param string $key
	 */
	protected function unsetTemplateData( $key ) {
		unset( $this->templateData[ $key ] );
	}

	/**
	 * Возвращает тип ответа. На данный момент поддерживается html и json. 
	 * Первый идёт через twig, второй через json_encode
	 * 
	 * @return string
	 */
	public function getAnswerType() {
		return $this->answerType;
	}

	/**
	 * Возвращает строку json для отправки, если мы работаем с этим методом
	 * @return string
	 */
	public function getJson() {
		return json_encode( $this->json, true );
	}

	/**
	 * Устанавливает тип ответа. На данный момент поддерживается html и json. 
	 * Первый идёт через twig, второй через json_encode
	 * 
	 * @return string
	 */
	public function setAnswerType( $answerType = 'html' ) {
		$this->answerType = $answerType;
	}

	/**
	 * Устанавливает данные для ответа типа JSON (и заодно сам тип ответа тоже)
	 * @param mixed $json
	 */
	public function setJson( $json ) {
		$this->json = $json;
		$this->setAnswerType( 'json' );
	}

	/**
	 * Возвращаем название метода, через который мы "входим" в контроллер.
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * Устанавливаем, какой метод в контроллере будет вызываться. 
	 * @param string $action
	 */
	public function setAction( string $action ) {
		$this->action = $action;
	}

	public function getStatusCode() {
		return $this->statusCode;
	}

	public function setStatusCode( $code ) {
		if ( !is_int( $code ) || $code > 600 || $code < 100 ) {
			throw new \InvalidArgumentException( '$code supposed to be int and valid http code' );
		}

		$this->statusCode = $code;
	}

}
