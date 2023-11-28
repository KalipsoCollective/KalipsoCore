<?php

/**
 * @package KX\Core
 * @subpackage Router
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Core\Helper;
use KX\Core\Exception;

final class Router
{

  private $routes = [];
  private $excludedRoutes = [];
  private $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
  private $endpoint = '';

  /**
   * Router constructor
   * @return object
   */
  public function __construct()
  {
    $this->endpoint = $_SERVER['REQUEST_URI'];
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
  public function addRoute(
    string|array $method,
    string $path,
    callable|string $controller = null,
    callable|array|string $middlewares = []
  ): object {

    if (is_null($controller)) {
      throw new \Exception('Controller is required in: ' . $path);
    }

    if (is_array($method)) {
      foreach ($method as $m) {
        $this->addRoute($m, $path, $controller, $middlewares);
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
   * Get routes
   * @return array
   */
  public function getRoutes(): array
  {
    return $this->routes;
  }
}
