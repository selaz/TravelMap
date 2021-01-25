<?php

namespace Selaz\Tools;

use Exception,
	Symfony\Component\Routing\RequestContext,
	Symfony\Component\Routing\Route,
	Symfony\Component\Routing\RouteCollection;

/**
 * Получение конфига маршрутов, на основе хоста. В конфиге мы явно указываем какой домен к какому классу маршрутов относится,
 * а тут просто создаем объект класса, выгребаем маршруты, и радуемся
 */
class RouteManager {

	/**
	 * @var RouteCollection
	 */
	private $routes;
	
	public function __construct(RequestContext $requestContext) {
		try {
			$this->routes = new RouteCollection();
			$config = Config::load('main.ini');
			
			$this->loadRoutes($config->get('host','web') ?? $requestContext->getHost());
		} catch ( \Exception $e ) {
			throw new Exception( sprintf( 'Что-то пошло не так с маршрутизацией: %s', $e->getMessage() ) );
		}
	}

	/**
	 * Возвращает маршруты для текущего хоста
	 * 
	 * @return RouteCollection
	 */
	public function getRoutes() {
		return $this->routes;
	}
	
	
	public function loadRoutes(string $host) {
		
		$c = Config::load('routes.yml');
		
		foreach ( $c->getBlock($host) ?? [] as $route) {
			$hash = md5(json_encode([$route]));
			$route = new Route($route['route'], $route['defaults'], $route['requirements'] ?? [], $route['options'] ?? [] );
			
			$this->routes->add($hash, $route);
		}
	}

}