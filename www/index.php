<?php

include_once( realpath( __DIR__ . '/../vendor/autoload.php' ) );

use \Selaz\Exceptions\HttpException,
	\Selaz\Runners\Index;

$runner = new Index();
try {
	$runner->loadConfig(); // конфиги базы, роутов и т.п.
	$runner->loadRequest(); // обработка запроса
	$runner->routing(); // определение контроллера и действия
	$runner->preController(); // общие для всех контроллеров проекта действия
	$runner->controller(); // вызов методов контроллера (preAction, сам Action и postAction)
	$runner->response(); // подготовка ответа (twig/json)
	$runner->postController(); // действия после контроллера для проекта
} catch ( HttpException $e ) {
	$runner->httpExceptionCatch( $e ); // нормальные http ответы, кроме 200 @todo 301
} catch ( Exception $e ) {
	$runner->exceptionCatch( $e ); // а это совсем косяки
}
$runner->sendAndFinish(); // конец