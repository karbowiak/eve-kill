<?php
$container = $app->getContainer();

/**
 * View Renderer
 * @param $c
 *
 * @return \Slim\Views\Twig
 */
$container['view'] = function ($c) {
	$settings = $c->get('settings')['view'];

	$view = new \Slim\Views\Twig($settings["template_path"], $settings["twig"]);

	// Extensions
	$view->addExtension(new \Slim\Views\TwigExtension($c->get("router"), $c->get("request")->getUri()));
	$view->addExtension(new Twig_Extension_Debug());

	return $view;
};

/**
 * Monolog Logger
 *
 * @param $c
 *
 * @return \Monolog\Logger
 */
$container['logger'] = function ($c) {
	$settings = $c->get('settings')['logger'];
	$logger = new Monolog\Logger($settings['name']);
	$logger->pushProcessor(new Monolog\Processor\UidProcessor());
	$logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));

	return $logger;
};

/**
 * Symfony Cache
 *
 * @param $c
 *
 * @return \Symfony\Component\Cache\Adapter\FilesystemAdapter
 */
$container["cache"] = function ($c) {
	$settings = $c->get("settings")["cache"];
	$cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter("ek", 3600, $settings["location"]);

	return $cache;
};

/**
 * @param $c
 *
 * @return \App\Lib\DatabaseAsync
 */
$container["dbAsync"] = function($c) {
	$settings = $c->get("settings")["database"];
	$cache = $c->get("cache");
	$log = $c->get("logger");

	return new \App\Lib\DatabaseAsync($cache, $settings, $log);
};

/**
 * @param $c
 *
 * @return \App\Lib\Database
 */
$container["db"] = function ($c) {
	$settings = $c->get("settings")["database"];
	$cache = $c->get("cache");
	$log = $c->get("logger");
	return new \App\Lib\Database($cache, $settings, $log);
};