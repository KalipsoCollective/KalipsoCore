<?php

/**
 * @package KX\Core
 * @subpackage Router
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Core\Helper;
use KX\Core\Exception;
use stdClass;

final class Router
{

    private $routes = [];
    private $attributes = [];
    private $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
    private $endpoint = '';
    private $route = null;
    private $routeDetails = null;
    private $method = null;
    public  $methodNotAllowed = false;

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
                    throw new \Exception('Invalid method: ' . $m . ' in: ' . $path);
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
     * Get route details
     * @return array|null
     */
    public function getRouteDetails(): array|null
    {
        return $this->routeDetails;
    }

    /**
     * Get route
     * @return string|null
     */
    public function getRoute(): string|null
    {
        return $this->route;
    }

    /**
     * Get attributes
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /** 
     * Run router
     * @return object
     */
    public function run(): object
    {

        $this->route = null;

        if (isset($this->routes[$this->endpoint]) === false) {

            $fromCache = false;
            if (Helper::config('settings.route_cache')) {
                $routeHash = md5(trim($this->endpoint, '/'));

                if (file_exists($file = Helper::path('app/Storage/route_cache/' . $routeHash . '.json'))) {
                    $routeCache = json_decode(file_get_contents($file), true);
                    $this->attributes = $routeCache['attributes'];
                    $routePath = $routeCache['routePath'];
                    $route = $routeCache['route'];
                    $fromCache = true;
                }
            }

            if (!$fromCache) {

                $detectedRoutes = [];
                foreach ($this->routes as $path => $details) {

                    /**
                     *
                     * Catch attributes
                     **/
                    if (strpos($path, ':') !== false) {

                        $explodedPath = trim($path, '/');
                        $explodedRequest = trim($this->endpoint, '/');

                        $explodedPath = strpos($explodedPath, '/') !== false ?
                            explode('/', $explodedPath) : [$explodedPath];

                        $explodedRequest = strpos($explodedRequest, '/') !== false ?
                            explode('/', $explodedRequest) : [$explodedRequest];


                        /**
                         * when the format equal 
                         **/
                        if (($totalPath = count($explodedPath)) === count($explodedRequest)) {

                            preg_match_all(
                                '@(:([a-zA-Z0-9_-]+))@m',
                                $path,
                                $expMatches,
                                PREG_SET_ORDER,
                                0
                            );

                            $expMatches = array_map(function ($v) {
                                return $v[0];
                            }, $expMatches);
                            $total = count($explodedPath);
                            foreach ($explodedPath as $pathIndex => $pathBody) {

                                if ($pathBody === $explodedRequest[$pathIndex] || in_array($pathBody, $expMatches) !== false) { // direct directory check

                                    if (in_array($pathBody, $expMatches) !== false) {
                                        // extract as attribute
                                        $this->attributes[ltrim($pathBody, ':')] = Helper::filter($explodedRequest[$pathIndex]);
                                    }

                                    if ($totalPath === ($pathIndex + 1)) {
                                        $route = $details;
                                        $routePath = $path;
                                        $detectedRoutes[$path] = $details;
                                    }
                                } else {
                                    break;
                                }
                            }
                        }
                    }
                }

                if (count($detectedRoutes) > 1) {

                    $uri = $this->endpoint;
                    $similarity = [];
                    foreach ($detectedRoutes as $pKey => $pDetail) {

                        $pKeyFormatted = preg_replace('@(:([a-zA-Z0-9_-]+))@m', '', $pKey);
                        $pKeyFormatted = str_replace('//', '/', $pKeyFormatted);
                        similar_text($pKeyFormatted, $this->endpoint, $perc);
                        $similarity[$pKey] = $perc;
                    }

                    arsort($similarity, SORT_NUMERIC);
                    $useRoute = array_key_first($similarity);

                    if (isset($this->routes[$useRoute][$this->method]) === false) {
                        throw new \Exception('Method not allowed: ' . $this->method, 405);
                    } else {
                        $route = $detectedRoutes[$useRoute][$this->method];
                        $routePath = $useRoute;
                    }

                    if (Helper::config('settings.route_cache')) {

                        // dir check
                        if (!is_dir(Helper::path('app/Storage/route_cache'))) {
                            mkdir(Helper::path('app/Storage/route_cache'), 0777, true);
                        }

                        file_put_contents(
                            Helper::path('app/Storage/route_cache/' . $routeHash . '.json'),
                            json_encode([
                                'attributes' => $this->attributes,
                                'routePath' => $routePath,
                                'route' => $route
                            ])
                        );
                    }
                }

                if (!empty($route)) {
                    // method not allowed
                    if (isset($route[$this->method]) === false) {
                        throw new \Exception('Method not allowed: ' . $this->method, 405);
                    }
                    $this->route = $routePath;
                    $this->routeDetails = $route;
                }
                // route not found
            } else {
                $this->route = $routePath;
                $this->routeDetails = $route;
            }
        } else {
            if (isset($this->routes[$this->endpoint][$this->method]) === false) {
                $this->methodNotAllowed = true;
            } else {
                $this->route = $this->endpoint;
                $this->routeDetails = $this->routes[$this->endpoint][$this->method];
            }
        }


        return $this;
    }

    /**
     * Get instance
     * @return object
     */
    public static function getInstance(): object
    {
        return new self();
    }
}
