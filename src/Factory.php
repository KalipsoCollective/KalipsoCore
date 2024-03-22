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

    protected $defaultViewLayout;
    protected $defaultViewFolder;
    protected $errorPageContents;

    protected $logRecord = true;

    protected $maintenanceMode = [
        'excludedRoutes' => [],
        'bypassEndpoint' => ''
    ];

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
     * Set error page contents
     * @param string $content
     * @return object
     */
    public function setErrorPageContents(array $content): object
    {
        $this->errorPageContents = $content;
        return $this;
    }

    /**
     * Factory constructor
     * @return object
     */
    public function setup(): object
    {
        global $kxAuthToken, $kxAvailableLanguages;

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

            // session maxlife (default 30 days)
            ini_set('session.gc_maxlifetime', (60 * 60 * 24 * 30));

            /**
             * Set session name
             **/
            $sessionName = Helper::config('SESSION_NAME');
            if (!empty($sessionName)) {
                session_name((string) $sessionName);
            }
            session_start([
                'read_and_close' => true,
            ]);
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
         * Set default app name
         **/
        $appName = Helper::config('settings.name');
        if (empty($appName)) {
            $appName =
                Helper::config('APP_NAME');

            if (empty($appName)) {
                $appName = 'KalipsoCore';
            }
        }
        define('APP_NAME', $appName);

        /**
         * Set language
         **/

        $kxAvailableLanguages = explode(',', (string)Helper::config('AVAILABLE_LANGUAGES'));
        if (empty($kxAvailableLanguages)) {
            $kxAvailableLanguages = ['en'];
        }

        $setLang = null;
        $langSetted = false;

        // session
        if (isset($_SESSION['KX_LANG']) !== false) {
            $lang = $_SESSION['KX_LANG'];
            if (in_array($lang, $kxAvailableLanguages)) {
                $langSetted = true;
                $setLang = $lang;
            }
        }

        // get 
        if (!$langSetted && isset($_GET['lang']) !== false) {
            $lang = $_GET['lang'];
            if (in_array($lang, $kxAvailableLanguages)) {
                $langSetted = true;
                $setLang = $lang;
            }
        }

        // header X-Language
        if (!$langSetted && isset($_SERVER['HTTP_X_LANGUAGE']) !== false) {
            $lang = $_SERVER['HTTP_X_LANGUAGE'];
            if (in_array($lang, $kxAvailableLanguages)) {
                $langSetted = true;
                $setLang = $lang;
            }
        }

        // default
        $defaultLang = Helper::config('DEFAULT_LANGUAGE');
        if (!$langSetted && !empty($defaultLang)) {
            $setLang = $defaultLang;
        }

        // set session
        if ($setLang) {
            Helper::setLang($setLang);
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
        $kxAuthToken = Helper::authToken();

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
     * @param bool $effectAllSubRoutes
     * @return object
     */
    public function routeGroup(array $mainRoute, array $subRoutes, bool $effectAllSubRoutes = false): object
    {

        // add main route
        $this->route(
            ...$mainRoute
        );

        foreach ($subRoutes as $subRoute) {

            $subMiddlewares = isset($subRoute[3]) !== false ? $subRoute[3] : [];
            if (is_string($subMiddlewares)) {
                $subMiddlewares = [$subMiddlewares];
            }
            if ($effectAllSubRoutes &&  isset($mainRoute[3]) !== false) {
                $mainMiddlewares = $mainRoute[3];
                if (is_string($mainMiddlewares)) {
                    $mainMiddlewares = [$mainMiddlewares];
                }
                $subMiddlewares = array_merge($mainMiddlewares, $subMiddlewares);
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
     * Set default view folder
     * @param string $folder
     * @return object
     */
    public function setDefaultViewFolder(string $folder): object
    {
        $this->defaultViewFolder = $folder;
        return $this;
    }

    /**
     * Set default view layout
     * @param string $layout
     * @return object
     */
    public function setDefaultViewLayout(string $layout): object
    {
        $this->defaultViewLayout = $layout;
        return $this;
    }

    /**
     * Run the application
     * @return void
     */
    public function run()
    {
        global $kxVariables, $kxLang;

        $this->checkIPBlock();
        $this->startRateLimit();
        $notFound = false;
        $methodNotAllowed = false;

        // detect route
        $this->router->run();

        if (file_exists(Helper::path('app/External/variables.php'))) {
            $kxVariables = require Helper::path('app/External/variables.php');
        }

        $redirect = false;
        if ($this->router->getRouteDetails()) {

            // apply middlewares
            $next = true;

            if (isset($this->router->getRouteDetails()['middlewares'])) {
                foreach ($this->router->getRouteDetails()['middlewares'] as $m) {

                    $this->request->setRouteDetails(
                        $this->router->getRoute(),
                        $this->router->getRouteDetails(),
                        $this->router->getAttributes()
                    );

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
                            $this->response,
                            $this
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

            $maintenanceMode = Helper::config('settings.maintenance_mode', true);
            if (
                $maintenanceMode && (
                    !Helper::authorization($this->maintenanceMode['bypassEndpoint']) &&
                    in_array(
                        $this->router->getRoute(),
                        $this->maintenanceMode['excludedRoutes']
                    ) === false
                )
            ) {
                $desc = Helper::config('settings.maintenance_mode_desc');
                $desc = json_decode(
                    (string)$desc,
                    true
                );
                $this->errorPage([
                    'code' => 503,
                    'title' => Helper::lang('base.maintenance_mode'),
                    'description' => Helper::lang('base.maintenance_mode'),
                    'subText' => $desc[$kxLang],
                ]);
            }

            if ($next && isset($this->router->getRouteDetails()['controller']) !== false) {

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
            } else {

                if ($this->router->methodNotAllowed) {
                    $methodNotAllowed = true;
                } else {
                    $notFound = true;
                }
            }
        } else {

            if ($this->router->methodNotAllowed) {
                $methodNotAllowed = true;
            } else {
                $notFound = true;
            }
        }

        if ($methodNotAllowed) {
            $this->errorPage(405);
        } elseif ($notFound) {
            $this->errorPage(404);
        }
        // log
        $this->saveLog();

        if ($redirect) {
            $this->response->runRedirection();
        }
    }

    /**
     * Log record
     */
    public function saveLog()
    {
        if (strpos($this->request->getUri(), '.map')) {
            return;
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
        } else {
            $log = false;
        }

        if (isset($this->logRecord) && $this->logRecord === false) {
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
        if ($limit && in_array($rateDriver, ['file', 'redis']) && in_array(
            $this->request->getRequestMethod(),
            ['GET', 'POST', 'PUT']
        )) {

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
                // accept
                if ($this->request->getHeader('Accept') === 'application/json') {
                    $this->response->json([
                        'status' => false,
                        'notify' => [
                            [
                                'type' => 'error',
                                'message' => Helper::lang('error.too_many_requests') . '<br>' . Helper::lang('error.too_many_requests_sub_text') . ' ' . date('d.m.Y H:i:s', (int)$reset)
                            ]
                        ],
                        'error' => [
                            'code' => 429,
                        ]
                    ], 429);
                } else {
                    $this->errorPage([
                        'code' => 429,
                        'title' => '429' . ' - ' . Helper::lang('error.too_many_requests'),
                        'description' => Helper::lang('error.too_many_requests'),
                        'subText' => Helper::lang('error.too_many_requests_sub_text') . ' ' . date('d.m.Y H:i:s', (int)$reset)
                    ]);
                }
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
                    $this->errorPage(403);
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
                    $this->errorPage(403);
                    exit;
                }
            }
        }
    }

    /**
     * Error Page Output
     * @param string|int|array $statusCodeOrData
     * @return void
     */
    public function errorPage(string|int|array $statusCodeOrData)
    {
        $excuted = false;
        if (is_array($statusCodeOrData)) {
            if (
                is_numeric($statusCodeOrData['code'])
            ) {
                $this->response->setStatus($statusCodeOrData['code']);
            }
            if ($this->defaultViewFolder) {
                $this->response->render(
                    $this->defaultViewFolder . '/error',
                    $statusCodeOrData,
                    $this->defaultViewLayout ?? 'error'
                );
            } else {
                $this->response->send('<pre>' . $statusCodeOrData['description'] . '</pre>');
            }
            $excuted = true;
        } else {
            $statusCode = (string) $statusCodeOrData;
        }
        if (!$excuted) {
            $defaultErrorPageContents = [
                '400' => [
                    'code' => 400,
                    'title' => '400 - ' . Helper::lang('error.bad_request'),
                    'description' => Helper::lang('error.bad_request'),
                    'subText' => Helper::lang('error.bad_request_sub_text')
                ],
                '401' => [
                    'code' => 401,
                    'title' => '401 - ' . Helper::lang('error.unauthorized'),
                    'description' => Helper::lang('error.unauthorized'),
                    'subText' => Helper::lang('error.unauthorized_sub_text')
                ],
                '403' => [
                    'code' => 403,
                    'title' => '403 - ' . Helper::lang('error.forbidden'),
                    'description' => Helper::lang('error.forbidden'),
                    'subText' => Helper::lang('error.forbidden_sub_text')
                ],
                '404' => [
                    'code' => 404,
                    'title' => '404 - ' . Helper::lang('error.not_found'),
                    'description' => Helper::lang('error.not_found'),
                    'subText' => Helper::lang('error.not_found_sub_text'),
                    'link' => [
                        'text' => Helper::lang('base.back_to_home'),
                        'url' => Helper::base()
                    ]
                ],
                '405' => [
                    'code' => 405,
                    'title' => '405 - ' . Helper::lang('error.method_not_allowed'),
                    'description' => Helper::lang('error.method_not_allowed'),
                    'subText' => Helper::lang('error.method_not_allowed_sub_text')
                ],
                '500' => [
                    'code' => 500,
                    'title' => '500 - ' . Helper::lang('error.internal_server_error'),
                    'description' => Helper::lang('error.internal_server_error'),
                    'subText' => Helper::lang('error.internal_server_error_sub_text')
                ],
                '503' => [
                    'code' => 503,
                    'title' => '503 - ' . Helper::lang('error.service_unavailable'),
                    'description' => Helper::lang('error.service_unavailable'),
                    'subText' => Helper::lang('error.service_unavailable_sub_text')
                ],
            ];
            $pageData = null;
            if (isset($this->errorPageContents[$statusCode])) {
                $pageData = $this->errorPageContents[$statusCode];
            } else {
                $pageData = $defaultErrorPageContents[$statusCode];
            }

            $this->response->setStatus($pageData['code']);

            if ($this->defaultViewFolder) {
                $this->response->render(
                    $this->defaultViewFolder . '/error',
                    $pageData,
                    $this->defaultViewLayout ?? 'error'
                );
            } else {
                $this->response->send('<pre>' . $pageData['description'] . '</pre>');
            }
        }
    }

    /**
     * Set maintenance mode
     * @param array $excludedRoutes
     * @param string|null $bypassEndpoint
     * @return object
     */
    public function setMaintenanceMode(array $excludedRoutes, string $bypassEndpoint): object
    {
        $this->maintenanceMode = [
            'excludedRoutes' => $excludedRoutes,
            'bypassEndpoint' => $bypassEndpoint
        ];
        return $this;
    }

    /**
     * Set log record
     * @param bool $logRecord
     * @return object
     */
    public function setLogRecord(bool $logRecord): object
    {
        $this->logRecord = $logRecord;
        return $this;
    }
}
