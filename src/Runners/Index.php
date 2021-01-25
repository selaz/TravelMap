<?php

/**
 * Основной раннер для проектов
 * Пока заточен под использование в index.php и последовательность вызовов
 * В целом - можно расширять-наследовать под другие точки входа, если понадобятся
 * Пока же смотреть комменты к методам, где что непонятн
 * Сейчас идея в том, что раннер это именно набор несвязенных друг с другом методов, которые ты в точке входа дергаешь
 * когда надо и какие надо. Если напихать в раннер связей между методами - будет вообще другая логика.
 */

namespace Selaz\Runners;

use Selaz\Controllers\Controller,
	Selaz\Tools\RouteManager,
	Selaz\Exceptions\HttpException,
	Symfony\Component\HttpFoundation\Request,
	Symfony\Component\HttpFoundation\Response,
	Symfony\Component\Routing\Exception\ResourceNotFoundException,
	Symfony\Component\Routing\Matcher\UrlMatcher,
	Symfony\Component\Routing\RequestContext;

class Index {

	/** @var \Symfony\Component\HttpFoundation\Response $response */
	protected $response;

	/** @var \Symfony\Component\HttpFoundation\Request $request */
	protected $request;

	/** @var \Selaz\Tools\Config $config */
	protected $config;

	/** @var \Selaz\Controllers\Controller */
	protected $controller;
	protected $params = [];

	protected $possibleExceptionsHttpCode = [
		301, #Постоянный редирект
		302, #Временный редирект
		400, #Запрос невалидный.
		401, #Ошибка авторизации.
		403, #Превышено ограничение на доступ к ресурсам. | У вас нет доступа к этому ресурсу.
		404, #Указанный в запросе объект не найден.
		405, #Используемый метод не поддерживается.
		422, #В запросе отсутствует обязательный параметр. | Значение параметра, указанного в запросе, не соответствует формату. 
		500  #Внутренняя ошибка сервера.
	];
	
	/**
	 * Создаем базовые объекты
	 * Если они определены заранее - можно передать готовые
	 * @param Response $response
	 */
	public function __construct( Response $response = null) {
		$this->response = ( $response ) ?? new Response();
	}
	
	/**
	 * Отправка ответа и финальные действия после отправки этого ответа, если нужны
	 */
	public function sendAndFinish() {
		//Отсылаем ответ
		$this->response->send();
		// и если чего-то контроллеру надо - даем ему шанс сделать это
		if ( is_a( $this->controller, Controller::class ) ) {
			$this->controller->finish( $this->response );
		}
	}

	/**
	 * Инициация роутера и поиск нужного роута с получением оттуда параметров
	 * А также проверка редиректов
	 */
	public function routing() {
		// Для UrlMatcher
		$requestContext = new RequestContext();
		$requestContext->fromRequest( $this->request );

		$routesList = new RouteManager($requestContext);

		// Ищем совпадение url в маршрут, и выгребаем данные о контроллере\экшене
		try {
			$matcher = new UrlMatcher( $routesList->getRoutes(), $requestContext );
			$this->params = $matcher->match( $requestContext->getPathInfo() );	
		} catch ( ResourceNotFoundException $e ) {
			$mess = (empty($e->getMessage())) ? 'No route found' : $e->getMessage();
			throw new HttpException($mess, 404 );
		}
	}

	public function setController( Controller $controller ) {
		$this->controller = $controller;
	}

	/**
	 * Действия, которые будут выполнены для всех контроллеров проекта ДО экшна контроллера
	 * Вся логика методов - внутри лоадеров
	 */
	public function preController() {
	}

	/**
	 * Действия, которые будут выполнены для всех контроллеров проекта ПОСЛЕ экшна контроллера
	 * Вся логика методов - внутри лоадеров
	 */
	public function postController() {
	}

	/**
	 * Инициализация контроллера, преЭкшн, Экшн и постЭкшн
	 * Вся логика методов - внутри контроллеров
	 * Здесь же пачка важных сетов для контроллеров перед всеми этими методами.
	 */
	public function controller( string $manualAction = null ) {
		// Создаем контроллер
		if ( !is_a( $this->controller, Controller::class ) ) {
			$controller = $this->params[ 'controller' ];
			
			if (class_exists($controller)) {
				$this->controller = new $controller;
			} else {
				throw new HttpException(sprintf('Controller %s not foud', $controller), 503);
			}
		}
		$action = ( $manualAction ) ?? $this->params[ 'action' ];

		if ( method_exists( $this->controller, $action ) ) {
			// "прокидка" нужных штук в контроллеры
			$this->controller->setRequest( $this->request );
			$this->controller->setParams( $this->params );
			$this->controller->setResponseHeaders( $this->response->headers );

			// и только теперь отдаем управление контроллерам
			$this->controller->setAction( $action );
			$this->controller->preAction();

			call_user_func( array( $this->controller, $action ) );

			$this->controller->postAction();
			
			foreach ( $this->controller->getHeaders() as $key => $value ) {
				$this->response->headers->set( $key, $value );
			}
			
		} else {
			throw new HttpException( 'Action ' . $action . ' does not exists', 503 );
		}
	}

	/**
	 * Подготовка данных для вывода
	 * Сами данные у нас лежат в контроллере и подразумевается, что мы их там сгенерировали заранее.
	 * Там же мы уже определили виды ответа и всё такое, тут просто твиг/json уже
	 * И всё это дело мы в content ответа складываем, вместе с кодом ответа
	 */
	public function response() {
		if ( in_array( $this->controller->getAnswerType(), ['html', 'text'] ) ) {
			$pageData = '';
			if ( $this->controller->getTemplate() ) {
				$tplDir = realpath( __DIR__ . '/../../templates/' );
				
				$loader = new \Twig\Loader\FilesystemLoader($tplDir);

				$options = [
					'cache' => $tplDir . '/cache',
					'auto_reload' => true,
					'debug' => true,
				];
				
				$twig = new \Twig\Environment($loader,$options);
				$twig->addExtension(new \Twig\Extension\DebugExtension());

				//Рендерим страницу с полученными от контроллера данными
				$pageData = $twig->render(
						$this->controller->getTemplate(), $this->controller->getTemplateData()
				);
			}
			//Устанавливаем ответ
			if ( $this->controller->getAnswerType() == 'text' ) {
				$this->response->headers->set( 'Content-Type', 'text/plain;' );
			}
			
			$this->response->setContent( $pageData );
		} elseif ( $this->controller->getAnswerType() == 'json' ) {
			$this->response->headers->set( 'Content-Type', 'application/json;charset=utf-8' );
			$this->response->setContent( $this->controller->getJson() );
		}  elseif ( $this->controller->getAnswerType() == 'jpg' ) {
			$this->response->headers->set( 'Content-Type', 'image/jpeg' );
			$this->response->setContent( $this->controller->getTemplateDataItem('bin') );
		}

		$this->response->setStatusCode( $this->controller->getStatusCode() );
	}
	
	/**
	 * Инициализация объекта-реквеста
	 */
	public function loadRequest() {
		$this->request = Request::createFromGlobals();
	}

	/**
	 * Инициализация конфига
	 */
	public function loadConfig() {
	}

	/**
	 * Обработка http-шных ответов, кроме 200
	 * @TODO тут как-то всё странно у нас, редиректы вынесены отдельно, хотя могут и сюда прилететь,
	 * 404 по сути тоже отдельная, надо бы порефакторить потом это хорошенько
	 * @param HttpException $e
	 */
	public function httpExceptionCatch( HttpException $e ) {
			if ( $e->getHeaders() ) {
				foreach ( $e->getHeaders() as $key => $value ) {
					$this->response->headers->set( $key, $value );
				}
			}

			$content = $e->getMessage();
			if ( isset( $this->controller ) && $this->controller->getAnswerType() == 'json' ) {
				$this->response->headers->set( 'Content-Type', 'application/json;charset=utf-8' );
				$content = [ 'errors' => [ $content ] ];
				$content = json_encode( $content );
			}

			$this->response->setStatusCode( $e->getCode() );
			$this->response->setContent( $content );
	}

	/**
	 * Обработка всех, кроме http-ных исключений
	 * @param \Exception $e
	 */
	public function exceptionCatch( \Exception $e ) {
		$this->response->setContent( sprintf( "%s<hr>\n%s", $e->getMessage(), $e->getTraceAsString() ) );
		$this->response->setStatusCode( Response::HTTP_SERVICE_UNAVAILABLE);
	}
	

}