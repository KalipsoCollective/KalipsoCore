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

	/**
	 * Constructor
	 * @return void
	 */
	public function __construct()
	{
		/**
		 * Basic constants
		 **/
		define('KX_START', microtime(true)); // We can use it for the execution time recorded in the log.
		define('KX_ROOT',  rtrim($_SERVER["DOCUMENT_ROOT"], '/') . '/');
		define('KX_CORE_VERSION', '0.0.1');
	}

	/**
	 * Factory constructor
	 * @return void
	 */
	public function setup()
	{

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

		if (Helper::config('env.DEV_MODE')) {
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

		/**
		 * php.ini set and error reporting setting
		 **/
		ini_set('display_errors', 'on');
		error_reporting(E_ALL);

		return $this;
	}

	/**
	 * Add route
	 * @param string $method
	 * @param string $path
	 * @param string $controller
	 * @param array $middlewares
	 * @return void
	 */
	public function route(string $method, string $path, string $controller, array $middlewares = [])
	{
		$this->routes[] = [
			'method' => $method,
			'path' => $path,
			'controller' => $controller,
			'middlewares' => $middlewares
		];

		return $this;
	}

	/**
	 * Run the application
	 * @return void
	 */
	public function run()
	{
		echo KX_ROOT;
	}
}
