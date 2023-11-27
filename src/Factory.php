<?php

/**
 * @package KX\Core
 * @subpackage Factory
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Core\Helper;
use KX\Core\Exception;

/**
 * Factory class
 *
 * @package KX
 * @subpackage Core\Factory
 */

final class Factory
{

	protected $routes = [];
	protected $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

	/**
	 * Constructor
	 * @return object
	 */
	public function __construct()
	{
		/**
		 * Basic constants
		 **/
		define('KX_START', microtime(true)); // We can use it for the execution time recorded in the log.
		define('KX_ROOT',  rtrim($_SERVER["DOCUMENT_ROOT"], '/') . '/');
		define('KX_CORE_VERSION', '0.0.1');

		return $this;
	}

	/**
	 * Factory constructor
	 * @return object
	 */
	public function setup(): object
	{

		/**
		 * Output buffer start
		 **/
		ob_start();

		/**
		 * Shutdown function registration
		 **/
		register_shutdown_function(function () {
			Exception::fatalHandler();
		});

		/**
		 * Error handler set
		 **/
		set_error_handler(
			function ($level, $error, $file, $line) {
				if (0 === error_reporting()) {
					return false;
				}
				Exception::errorHandler($level, $error, $file, $line);
			},
			E_ALL
		);

		/**
		 * Exception handler set
		 **/
		set_exception_handler(function ($e) {
			Exception::exceptionHandler($e);
		});

		/**
		 * Load config
		 */
		Helper::loadConfig();

		if (Helper::config('DEV_MODE')) {
			/**
			 * Debug mode
			 **/
			ini_set('display_errors', 'on');
			error_reporting(E_ALL);
		} else {
			/**
			 * Production mode
			 **/
			ini_set('display_errors', 'off');
			error_reporting(0);
		}

		if ($timezone = Helper::config('TIMEZONE')) {
			/**
			 * Timezone setting
			 **/
			date_default_timezone_set((string) $timezone);
		}

		/**
		 * Auth strategy
		 **/
		if (Helper::config('AUTH_STRATEGY') === 'session') {

			/**
			 * Set session name
			 **/
			$sessionName = Helper::config('SESSION_NAME');
			if (!empty($sessionName)) {
				session_name((string) $sessionName);
			}
			session_start();
		} else {
			/**
			 * Set JWT secret
			 **/
			$jwtSecret = Helper::config('JWT_SECRET');
			if (!empty($jwtSecret)) {
				Helper::setJWTSecret((string) $jwtSecret);
			}
		}

		/**
		 * Set default language
		 **/
		$defaultLang = Helper::config('DEFAULT_LANGUAGE');
		if (!empty($defaultLang)) {
			Helper::setLang((string) $defaultLang);
		}

		return $this;
	}

	/**
	 * Add route
	 * @param string|array $method
	 * @param string $path
	 * @param callable|string $controller
	 * @param callable|array|string $middlewares
	 * @return object
	 */
	public function route(string|array $method, string $path, callable|string $controller, callable|array|string $middlewares = []): object
	{
		if (is_array($method)) {
			foreach ($method as $m) {
				$this->route($m, $path, $controller, $middlewares);
			}
			return $this;
		} else {

			if (is_string($middlewares)) {
				$middlewares = [$middlewares];
			}
			$middlewares = array_unique(
				$middlewares
			);

			if (empty($middlewares)) {
				$middlewares = null;
			}

			if (is_string($method)) {
				$method = [$method];
			}

			foreach ($method as $m) {
				if (!in_array($m, $this->methods)) {
					throw new \Exception('Invalid method: ' . $m);
				}
				$path = '/' . trim($path, '/');
				if (isset($this->routes[$path]) === false) {
					$this->routes[$path] = [];
				}
				$this->routes[$path][$m] = [
					'controller' => $controller,
					'middlewares' => $middlewares
				];
			}
		}

		return $this;
	}

	/**
	 * Add route group
	 * @param array $mainRoute
	 * @param array $subRoutes
	 * @return object
	 */
	public function routeGroup(array $mainRoute, array $subRoutes): object
	{

		// add main route
		$this->route(
			...$mainRoute
		);

		foreach ($subRoutes as $subRoute) {

			$subMiddlewares = isset($subRoute[3]) !== false ? $subRoute[3] : [];
			if (isset($mainRoute[3]) !== false) {
				$subMiddlewares = array_merge($subMiddlewares, $subMiddlewares);
			}

			$subRoute[1] = $mainRoute[1] . '/' . trim($subRoute[1], '/');
			$subRoute = [
				$subRoute[0],
				$subRoute[1],
				$subRoute[2],
				$subMiddlewares
			];

			// add sub route
			$this->route(
				...$subRoute
			);
		}

		return $this;
	}

	/**
	 * Add route from an array
	 * @param array $routes
	 * @return object
	 */
	public function routes(array $routes): object
	{
		foreach ($routes as $route) {
			$this->route(
				...$route
			);
		}

		return $this;
	}

	/**
	 * Run the application
	 * @return void
	 */
	public function run()
	{

		echo '<pre>';
		var_dump($this->routes);
		echo '</pre>';
		exit;
		echo KX_ROOT;
	}
}
