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
  private $route = null;
  private $routeDetails = null;
  private $method = null;

  /**
   * Router constructor
   * @return object
   */
  public function __construct()
  {

    $url = parse_url($_SERVER['REQUEST_URI']);
    $this->endpoint = '/' . trim(
      $url['path'] === '/' ? $url['path'] : rtrim($url['path'], '/'),
      '/'
    );
    $this->method = strtoupper(
      empty($_SERVER['REQUEST_METHOD']) ? 'GET' : $_SERVER['REQUEST_METHOD']
    );

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

  /**
   * Get method
   * @return string
   */
  public function getMethod(): string
  {
    return $this->method;
  }

  /**
   * Get endpoint
   * @return string|null
   */
  public function getRoute(): string|null
  {
    return $this->route;
  }

  /**
   * Get route details
   * @return array|null
   */
  public function getRouteDetails(): array|null
  {
    return $this->routeDetails;
  }

  /** 
   * Run router
   * @return object
   */
  public function run(): object
  {

    $this->route = null;



    if (isset($this->routes[$this->endpoint]) === false) {
      throw new \Exception('Route not found: ' . $this->endpoint, 404);
    }

    if (isset($this->routes[$this->endpoint][$this->method]) === false) {
      throw new \Exception('Method not allowed: ' . $this->method, 405);
    }

    $this->route = $this->endpoint;
    $this->routeDetails = $this->routes[$this->endpoint][$this->method];

    return $this;
  }
}
