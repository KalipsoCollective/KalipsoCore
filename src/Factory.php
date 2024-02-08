<?php

/**
 * @package KX\Core
 * @subpackage Factory
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Core\Helper;
use KX\Core\Log;
use KX\Core\Exception;
use KX\Core\Request;
use KX\Core\Router;
use KX\Core\Response;
use KX\Core\Middleware;
use Redis;

final class Factory
{
    protected $router;
    protected $request;
    protected $response;

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

        /**
         * Set Request and Response
         */
        $this->request = new Request();
        $this->response = new Response();
        $this->router = new Router();

        /**
         * Set powered by
         **/
        if (Helper::config('VISIBLE_POWERED_BY', true)) {
            $this->response->setHeader('X-Powered-By: KalipsoCore ' . KX_CORE_VERSION);
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
    public function route(
        string|array $method,
        string $path,
        callable|string $controller = null,
        callable|array|string $middlewares = []
    ): object {

        $this->router->addRoute(
            $method,
            $path,
            $controller,
            $middlewares
        );

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

        $this->checkIPBlock();
        $this->startRateLimit();


        // detect route
        $this->router->run();
        if ($this->router->getRouteDetails()) {

            // apply middlewares
            $next = true;
            $redirect = false;

            if (isset($this->router->getRouteDetails()['middlewares'])) {
                foreach ($this->router->getRouteDetails()['middlewares'] as $m) {

                    unset($middleware);
                    if ($m instanceof \Closure) {
                        $middleware = $m(
                            $this->request,
                            $this->response,
                            $this
                        );
                    } elseif (
                        is_string($m) &&
                        strpos($m, '@') !== false
                    ) {
                        $middlewareStr = explode(
                            '@',
                            $m,
                            2
                        );
                        $middlewareStr[0] = 'KX\\Middleware\\' . $middlewareStr[0];

                        $m = new $middlewareStr[0]();
                        $middleware = $m->{$middlewareStr[1]}(
                            $this->request,
                            $this->response
                        );
                    }

                    if (isset($middleware) && $middleware instanceof Middleware) {
                        if ($middleware->isNextCalled()) {

                            if (!empty($middleware->getParameters())) {
                                $this->request->setMiddlewareParams(
                                    $middleware->getParameters()
                                );
                            }
                        } else {
                            if ($middleware->isRedirectCalled()) {
                                $this->response->redirect(
                                    $middleware->redirect['url'],
                                    $middleware->redirect['statusCode']
                                );
                                $redirect = true;
                            }
                            $next = false;
                            break;
                        }
                    }
                }
            }

            if ($next) {
                if ($this->router->getRouteDetails()['controller'] instanceof \Closure) {
                    $this->router->getRouteDetails()['controller'](
                        $this->request,
                        $this->response,
                        $this
                    );
                } elseif (
                    is_string($this->router->getRouteDetails()['controller']) &&
                    strpos($this->router->getRouteDetails()['controller'], '@') !== false
                ) {
                    $controllerStr = explode(
                        '@',
                        $this->router->getRouteDetails()['controller'],
                        2
                    );
                    $controllerStr[0] = 'KX\\Controller\\' . $controllerStr[0];

                    $controller = new $controllerStr[0]();

                    $controller->{$controllerStr[1]}(
                        $this->request,
                        $this->response,
                        $this
                    );
                }
            }

            $logOption = Helper::config('LOG_LEVEL');
            if ($logOption === 'debug') {
                $log = true;
            } elseif ($logOption === 'error') {
                if ($this->response->getStatusCode() >= 400) {
                    $log = true;
                } else {
                    $log = false;
                }
            } elseif ($logOption === 'none') {
                $log = false;
            } else {
                $log = false;
            }

            // log  
            if ($log) {

                $log = new Log();
                $log->save(
                    $this->request,
                    $this->response
                );
            }

            if ($redirect) {
                $this->response->runRedirection();
            }
        } else {

            if ($this->router->methodNotAllowed) {
                $this->response->setStatus(405);
                $this->response->send('<pre>Method Not Allowed!</pre>');
            } else {
                $this->response->setStatus(404);
                $this->response->send('<pre>Not Found!</pre>');
            }
        }
    }

    /**
     * Set error handler
     * @param callable $handler
     * @return object
     */
    public function setCustomErrorHandler($handler): object
    {
        Exception::setErrorHandler($this->request, $this->response, $handler);
        return $this;
    }

    /**
     * Set layout
     * @param string|array $layout
     * @return object
     */
    public function setLayout(string|array $layout): object
    {
        $this->response->setLayout($layout);
        return $this;
    }

    /**
     * Start rate limit
     * @return void
     */
    private function startRateLimit()
    {
        $limit = (int) Helper::config('RATE_LIMIT');
        $rateDriver = Helper::config('RATE_LIMIT_DRIVER');
        if ($limit && in_array($rateDriver, ['file', 'redis']) && $this->request->getRequestMethod() === 'GET') {

            $ip = Helper::getIp();

            if ($rateDriver === 'file') {
                $jsonFile = KX_ROOT . 'app/Storage/rate_limit.json';
                if (file_exists($jsonFile)) {
                    $json = json_decode(file_get_contents($jsonFile), true);
                } else {
                    Helper::path('app/Storage/', true);
                    touch($jsonFile);
                    $json = [];
                }

                if (isset($json[$ip]) !== false && $json[$ip]['time'] < time() - 60) {
                    unset($json[$ip]);
                }

                if (isset($json[$ip]) === false) {
                    $json[$ip] = [
                        'time' => time(),
                        'count' => 0,
                    ];
                }

                $json[$ip]['count']++;
                $remaining = $limit - $json[$ip]['count'];
                $reset = $json[$ip]['time'] + 60;
                file_put_contents($jsonFile, json_encode($json, JSON_PRETTY_PRINT));
            } elseif ($rateDriver === 'redis' && class_exists('Redis')) {
                $redis = new Redis();
                $redis->connect(
                    Helper::config('REDIS_HOST')
                );
                if (!empty(Helper::config('REDIS_PASSWORD'))) {
                    $redis->auth(Helper::config('REDIS_PASSWORD'));
                }
                $redis->select((int)Helper::config('REDIS_DB'));
                $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);

                $key = 'rate_limiter_' . $ip;

                if ($redis->exists($key) && $redis->get($key)['time'] < time() - 60) {
                    $redis->del($key);
                }

                if (!$redis->exists($key)) {
                    $redis->set($key, [
                        'time' => time(),
                        'count' => 0,
                    ]);
                }

                $redisCache = $redis->get($key);

                $redisCache['count']++;

                $remaining = $limit - $redisCache['count'];
                $reset = $redisCache['time'] + 60;
                $redis->set($key, $redisCache);

                $redis->close();
            }

            $this->response->setHeader('X-RateLimit-Limit: ' . $limit);
            $this->response->setHeader('X-RateLimit-Remaining: ' . $remaining);
            $this->response->setHeader('X-RateLimit-Reset: ' . $reset);

            if ($remaining < 0) {
                throw new \Exception('Reset: ' . date('d.m.Y H:i:s', (int)$reset), 429, null);
                exit;
            }
        }
    }

    /**
     * Check IP block
     * @return void
     */
    private function checkIPBlock()
    {

        $ipBlocker = Helper::config('IP_BLOCKER', true);
        if (!$ipBlocker) {
            return;
        }

        $ip = Helper::getIp();
        $blockDriver = Helper::config('IP_BLOCKER_DRIVER');
        if (in_array($blockDriver, ['file', 'redis'])) {

            if ($blockDriver === 'file') {
                $jsonFile = KX_ROOT . 'app/Storage/ip_block.json';
                if (file_exists($jsonFile)) {
                    $json = json_decode(file_get_contents($jsonFile), true);
                } else {
                    Helper::path('app/Storage/', true);
                    touch($jsonFile);
                    $json = [];
                }

                file_put_contents($jsonFile, json_encode($json, JSON_PRETTY_PRINT));

                if (isset($json[$ip]) !== false) {
                    throw new \Exception('IP blocked!', 403, null);
                    exit;
                }
            } elseif ($blockDriver === 'redis' && class_exists('Redis')) {

                $redis = new Redis();
                $redis->connect(
                    Helper::config('REDIS_HOST')
                );
                if (!empty(Helper::config('REDIS_PASSWORD'))) {
                    $redis->auth(Helper::config('REDIS_PASSWORD'));
                }
                $redis->select((int)Helper::config('REDIS_DB'));
                $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);

                $key = 'ip_block_' . $ip;

                $isExists = $redis->exists($key);
                $redis->close();

                if ($isExists) {
                    throw new \Exception('IP blocked!', 403, null);
                    exit;
                }
            }
        }
    }
}
