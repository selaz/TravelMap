<?php

namespace Selaz\Tools;

use Exception;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Получение конфига маршрутов, на основе хоста. В конфиге мы явно указываем какой домен к какому классу маршрутов относится,
 * а тут просто создаем объект класса, выгребаем маршруты, и радуемся
 */
class RouteManager {

	/**
	 * @var RouteCollection
	 */
	private $routes;
	
	public function __construct() {
		$this->routes = new RouteCollection();
		$this->loadRoutes();
	}

	/**
	 * Возвращает маршруты для текущего хоста
	 * 
	 * @return RouteCollection
	 */
	public function getRoutes() {
		return $this->routes;
	}
	
	
	public function loadRoutes() {
		$c = yaml_parse_file(sprintf('%s/routes.yml',Env::getInstance()->getResourcePath()));
		
		foreach ( $c['routes'] ?? [] as $route) {
			$hash = md5(json_encode([$route]));
			$route = new Route($route['route'], $route['defaults'], $route['requirements'] ?? [], $route['options'] ?? [] );
			
			$this->routes->add($hash, $route);
		}
	}

}