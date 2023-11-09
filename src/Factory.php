<?php

/**
 * @package KX
 * @subpackage Core\Factory
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Core\Helper as Helper;
use KX\Core\Log;

final class Factory
{

    /**
     * All request details as object
     **/
    public $request;
    public $response;
    public $routes = [];
    public $excludedRoutes = [];
    public $endpoints = [];
    public $endpoint;
    public $lang = '';
    public $log = true;
    public $action;

    /**
     *  
     * Request handler 
     **/

    public function __construct()
    {

        global $languageFile;

        /**
         * Controller and middleware name for logs 
         */
        $this->action = (object)[
            'middleware' => [],
            'controller' => ''
        ];

        /**
         * Assign default language 
         **/
        $this->lang = Helper::config('settings.language');

        /**
         * 
         * X_POWERED_BY header - please don't remove! 
         **/

        Helper::http('powered_by');

        /**
         * 
         * KX_SESSION_NAME definition for next actions
         **/

        define('KX_SESSION_NAME', Helper::config('app.session'));


        /**
         * 
         * Start session and output buffering
         **/
        if (Helper::config('app.auth') === 'cookie') {
            Helper::sessionStart(); // It is created using KX_SESSION_NAME
        }
        ob_start();

        /**
         * 
         *  Handle request and method
         **/

        $this->response = (object)[
            'statusCode'    => 200,
            'status'        => true,
            'alerts'        => [],
            'redirect'      => [], // link, second, http status code
            'view'          => [] // as array: view parameters -> [0] = page, [1] = layout, as string: view_file, as json: null
        ];
        $this->request = (object)[];
        $this->request->params = [];
        $this->request->files = [];

        $url = parse_url($_SERVER['REQUEST_URI']);
        $this->request->uri = '/' . trim(
            $url['path'] === '/' ? $url['path'] : rtrim($url['path'], '/'),
            '/'
        );
        $this->request->method = strtoupper(
            empty($_SERVER['REQUEST_METHOD']) ? 'GET' : $_SERVER['REQUEST_METHOD']
        );

        /**
         * Clean GET parameters
         **/
        $langChanged = false;
        if (isset($_GET) !== false and count($_GET)) {

            foreach ($_GET as $key => $value) {
                $this->request->params[$key] = Helper::filter($value, 'script');

                if ($key === 'lang' and in_array($value, Helper::config('app.available_languages'))) {
                    $this->lang = $value;
                    $langChanged = true;
                }
            }
        }


        /**
         * Clean POST parameters
         **/

        if (isset($_POST) !== false and count($_POST)) {
            foreach ($_POST as $key => $value) {
                $this->request->params[$key] = Helper::filter($value, 'script');
            }
        }

        /**
         * 
         * Language definition 
         **/
        $sessionLanguageParam = Helper::getSession('language');
        if (
            $langChanged and
            $sessionLanguageParam != $this->lang and
            file_exists($path = Helper::path('app/Localization/' . $this->lang . '.php'))
        ) {

            $languageFile = require $path;
            Helper::setSession($this->lang, 'language');
        } elseif (
            is_null($sessionLanguageParam) and
            file_exists($path = Helper::path('app/Localization/' . $this->lang . '.php'))

        ) {

            $languageFile = require $path;
            Helper::setSession($this->lang, 'language');
        } elseif (
            !is_null($sessionLanguageParam) and
            file_exists($path = Helper::path('app/Localization/' . $sessionLanguageParam . '.php'))

        ) {

            $languageFile = require $path;
            $this->lang = $sessionLanguageParam;
        } else {
            throw new \Exception("Language file is not found!");
        }

        date_default_timezone_set(
            (string) Helper::lang('lang.timezone')
        );
    }


    /**
     *  Router register 
     *  @param string method        available method or methods(with comma)
     *  @param string route         link definition
     *  @param string controller    controller definition, ex: (AppController@index)
     *  @param array  middlewares   middleware definition, ex: ['CSRF@validate', 'Auth@with']
     *  @return this
     **/

    public function route($method, $route, $controller, $middlewares = [])
    {
        $methods = strpos($method, ',') ? explode(',', $method) : [$method];

        foreach ($methods as $method) {
            $detail = [
                'controller' => $controller,
                'middlewares' => $middlewares
            ];

            if (!count($detail['middlewares'])) {
                unset($detail['middlewares']);
            }

            $this->routes[$route][$method] = $detail;
        }


        return $this;
    }


    /**
     *  Sub router register 
     *  @param array root        available method or methods(with comma)
     *  @param string method        available method or methods(with comma)
     *  @param string route         link definition
     *  @param string controller    controller definition, ex: (AppController@index)
     *  @param array  middlewares   middleware definition, ex: ['CSRF@validate', 'Auth@with']
     *  @return this
     **/

    public function routeWithRoot($root, $method, $route, $controller, $middlewares = [])
    {
        $methods = strpos($method, ',') ? explode(',', $method) : [$method];

        foreach ($methods as $method) {
            $detail = [
                'controller' => $controller,
                'middlewares' => $middlewares
            ];

            if (!count($detail['middlewares'])) {
                unset($detail['middlewares']);
            }

            $this->routes[$root[1] . $route][$method] = $detail;
        }


        return $this;
    }


    /**
     * Multi route register 
     * @param routes -> multi route definition as array
     * @return this
     **/
    public function routes($routes)
    {

        foreach ($routes as $route)
            $this->route(...$route);

        return $this;
    }


    /**
     * Root-bound groupped route register
     * @param array root            root route definition
     * @param function subRoutes    sub route definitions
     * @return this
     **/
    public function routeGroup($root, $subRoutes)
    {

        // register root route
        $this->route(...$root);

        foreach ($subRoutes() as $route)
            $this->routeWithRoot($root, ...$route);

        return $this;
    }


    /**
     * Exclude while in maintenance
     * @param array routes            routes to exclude while in maintenance.
     * @return this
     **/
    public function excludeWhileInMaintenance($routes = [])
    {

        $this->excludedRoutes = $routes;

        return $this;
    }


    /**
     * 
     * App starter
     * @return this
     **/
    public function run()
    {

        // Include module routes
        if (file_exists($moduleFile = Helper::path('app/modules.php'))) {

            $module = require $moduleFile;
            if ($module and is_array($module)) {
                foreach ($module as $moduleKey => $moduleDetail) {

                    // listing route
                    if (isset($moduleDetail['routes']['listing'][$this->lang]) !== false) {
                        $this->route(...$moduleDetail['routes']['listing'][$this->lang]);
                    }

                    // detail route
                    if (isset($moduleDetail['routes']['detail'][$this->lang]) !== false) {
                        $this->route(...$moduleDetail['routes']['detail'][$this->lang]);
                    }
                }
            }
        }

        // Include form routes
        if (file_exists($formFile = Helper::path('app/forms.php'))) {

            $form = require $formFile;
            if ($form and is_array($form)) {
                foreach ($form as $formKey => $formDetail) {

                    // listing route
                    if (isset($formDetail['routes']['listing'][$this->lang]) !== false) {
                        $this->route(...$formDetail['routes']['listing'][$this->lang]);
                    }

                    // detail route
                    if (isset($formDetail['routes']['detail'][$this->lang]) !== false) {
                        $this->route(...$formDetail['routes']['detail'][$this->lang]);
                    }
                }
                $this->route('POST', '/form/:form/add', 'FormController@formAdd', []);
            }
        }

        // Route slug converter
        foreach ($this->routes as $route => $routeDetail) {

            if (strpos($route, '{') !== false and strpos($route, '}') !== false) {

                preg_match_all('/\{(.*?)\}/miu', $route, $matches, PREG_SET_ORDER, 0);
                if (count($matches)) {
                    unset($this->routes[$route]);
                    foreach ($matches as $match) {
                        $route = str_replace($match[0], (string) Helper::lang($match[1]), $route);
                    }
                }
            }
            $this->routes[$route] = $routeDetail;
        }

        // IP Block
        $blockList = file_exists($file = Helper::path('app/Storage/security/ip_blacklist.json')) ? json_decode(file_get_contents($file), true) : [];
        if (isset($blockList[Helper::getIp()]) !== false) {

            $this->response->statusCode = 403;
            $this->response->title = Helper::lang('err');
            $this->response->arguments = [
                'error' => '403',
                'output' => Helper::lang('error.ip_blocked')
            ];

            $this->response();

            return $this;
        }

        $notFound = true;

        /**
         * exact expression
         **/
        $route = null;
        if (isset($this->routes[$this->request->uri]) !== false) {
            $route = $this->routes[$this->request->uri];
            $this->endpoint = trim($this->request->uri, '/');
        }


        /**
         * Parse request 
         **/

        if (is_null($route)) {

            $fromCache = false;
            if (Helper::config('settings.route_cache')) {
                $routeHash = md5(trim($this->request->uri, '/'));

                if (file_exists($file = Helper::path('app/Storage/route_cache/' . $routeHash . '.json'))) {
                    $routeCache = json_decode(file_get_contents($file), true);
                    $this->request->attributes = $routeCache['attributes'];
                    $this->endpoint = $routeCache['endpoint'];
                    $route = $routeCache['details'];
                    $fromCache = true;
                    $notFound = false;
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
                        $explodedRequest = trim($this->request->uri, '/');

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
                                        $this->request->attributes[ltrim($pathBody, ':')] = Helper::filter($explodedRequest[$pathIndex]);
                                    }

                                    if ($totalPath === ($pathIndex + 1)) {
                                        $route = $details;
                                        $routePath = $path;
                                        $notFound = false;
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

                    $uri = $this->request->uri;
                    $similarity = [];
                    foreach ($detectedRoutes as $pKey => $pDetail) {

                        $pKeyFormatted = preg_replace('@(:([a-zA-Z0-9_-]+))@m', '', $pKey);
                        $pKeyFormatted = str_replace('//', '/', $pKeyFormatted);
                        similar_text($pKeyFormatted, $this->request->uri, $perc);
                        $similarity[$pKey] = $perc;
                    }

                    arsort($similarity, SORT_NUMERIC);
                    $useRoute = array_key_first($similarity);

                    $route = $detectedRoutes[$useRoute];
                    $routePath = $useRoute;
                }

                if (isset($routePath) !== false) {
                    $this->endpoint = trim($routePath, '/');
                }

                if (Helper::config('settings.route_cache')) {

                    if (!is_dir($dir = Helper::path('app/Storage'))) mkdir($dir);
                    if (!is_dir($dir = Helper::path('app/Storage/route_cache'))) mkdir($dir);

                    $cacheContent['attributes'] = $this->request->attributes;
                    $cacheContent['endpoint'] = $this->endpoint;
                    $cacheContent['details'] = $route;

                    file_put_contents($file, json_encode($cacheContent));
                }
            }
        } else {

            $notFound = false;
        }

        // 404
        if ($notFound) {

            $this->response->statusCode = 404;
            $this->response->title = Helper::lang('err');
            $this->response->arguments = [
                'error' => '404',
                'output' => Helper::lang('error.page_not_found')
            ];

            // Output
            $this->response();
        } else {

            // Maintenance Mode
            if (Helper::config('settings.maintenance_mode') and (!$this->authority('/management') and !in_array($this->endpoint, $this->excludedRoutes))) {

                $desc = Helper::config('settings.maintenance_mode_desc');
                $this->response->statusCode = 503;
                $this->response->title = Helper::lang('err');
                $this->response->arguments = [
                    'error' => '503',
                    'output' => $desc ? json_decode($desc, true) : $desc
                ];

                $this->response();
                return $this;
            }

            if (isset($route[$this->request->method]) !== false) {

                $route = $route[$this->request->method];

                /**
                 * 
                 * Middleware step
                 **/

                $next = true;

                if (isset($route['middlewares']) !== false) {

                    foreach ($route['middlewares'] as $middleware) {

                        // for log
                        $this->action->middleware[] = $middleware;

                        $middleware = explode('@', $middleware, 2);

                        if (isset($middleware[1]) === false) {
                            continue;
                        }

                        $method = $middleware[1];
                        $class = 'KX\\Middleware\\' . $middleware[0];

                        $middleware = (new $class(
                            $this
                        ))->$method();

                        /**
                         * Middleware alerts 
                         **/
                        if (isset($middleware['alerts']) !== false)
                            $this->response->alerts = array_merge(
                                $this->response->alerts,
                                $middleware['alerts']
                            );

                        /**
                         *  If we have alerts, we will display them on the next page with the session.
                         **/
                        if (isset($middleware['redirect']) !== false)
                            $this->response->redirect = $middleware['redirect'];


                        /**
                         * Arguments 
                         **/
                        if (isset($middleware['arguments']) !== false)
                            $this->response->arguments = $middleware['arguments'];

                        /**
                         * Change status code if middleware returns.
                         **/
                        if (isset($middleware['statusCode']) !== false)
                            $this->response->statusCode = $middleware['statusCode'];

                        /**
                         * A status token to use in some conditions, such as API responses. It must be boolean.
                         **/
                        $this->response->status = $middleware['status'];

                        if (!$middleware['next'])
                            $next = false;

                        if (!$middleware['status'])
                            break;
                    }
                }

                /**
                 * 
                 * Controller step
                 **/

                if ($next) {

                    if (isset($route['controller']) !== false) {

                        $this->action->controller = $route['controller'];

                        $controller = explode('@', $route['controller'], 2);

                        $method = $controller[1];
                        $class = 'KX\\Controller\\' . $controller[0];

                        $controller = (new $class(
                            $this
                        ))->$method();

                        /**
                         * Controller alerts 
                         **/
                        if (isset($controller['alerts']) !== false)
                            $this->response->alerts = array_merge(
                                $this->response->alerts,
                                $controller['alerts']
                            );

                        /**
                         *  If we have alerts, we will display them on the next page with the session.
                         **/
                        if (isset($controller['redirect']) !== false)
                            $this->response->redirect = $controller['redirect'];


                        /**
                         * Arguments 
                         **/
                        if (isset($controller['arguments']) !== false)
                            $this->response->arguments = $controller['arguments'];

                        /**
                         * Output 
                         **/
                        if (isset($controller['output']) !== false)
                            $this->response->output = $controller['output'];

                        /**
                         * Log 
                         **/
                        if (isset($controller['log']) !== false)
                            $this->log = $controller['log'];

                        /**
                         * Change status code if middleware returns.
                         **/
                        if (isset($controller['statusCode']) !== false)
                            $this->response->statusCode = $controller['statusCode'];

                        /**
                         * A status token to use in some conditions, such as API responses. It must be boolean.
                         **/
                        $this->response->status = $controller['status'];

                        if (isset($controller['view']) !== false)
                            $this->response->view = $controller['view'];
                        else
                            $this->response->view = null;
                    } else {

                        throw new \Exception((string) Helper::lang('error.controller_not_defined'));
                    }
                }

                // Output
                $this->response();
            } else { // 405

                $this->response->statusCode = 405;
                $this->response->title = Helper::lang('err');
                $this->response->arguments = [
                    'error' => '405',
                    'output' => Helper::lang('error.method_not_allowed')
                ];

                $this->response();
            }
        }

        return $this;
    }


    /**
     * Extract created response
     * @return void
     **/
    public function response()
    {

        Helper::http($this->response->statusCode);
        if ($this->response->statusCode === 503) {
            Helper::http('retry_after');
        }
        $next = true;

        if ($this->response->statusCode === 200) {

            if (!empty($this->response->redirect)) {

                $second = (is_array($this->response->redirect) ? $this->response->redirect[1] : null);
                if (empty($second)) {
                    $next = false;
                    if (count($this->response->alerts)) {
                        Helper::setSession($this->response->alerts, 'alerts');
                    }
                }

                Helper::http('refresh', [
                    'url' => (is_array($this->response->redirect) ? $this->response->redirect[0] : $this->response->redirect),
                    'second' => $second
                ]);
            }

            if ($next and $this->response->view !== '') {

                if (is_string($this->response->view)) {
                    $viewFile = $this->response->view;
                    $viewLayout = 'default';
                } elseif (is_array($this->response->view) and count($this->response->view) === 2) {
                    $viewFile = $this->response->view[0];
                    $viewLayout = $this->response->view[1];
                } elseif (is_null($this->response->view)) {
                    $viewFile = null;
                    $viewLayout = null;
                } else {
                    throw new \Exception((string) Helper::lang('error.view_definition_not_found'));
                }

                $this->view(
                    $viewFile,
                    $this->response->arguments,
                    $viewLayout
                );
            }
        } else {


            if (!empty($this->response->redirect)) {

                $second = (is_array($this->response->redirect) ? $this->response->redirect[1] : null);
                if (empty($second)) {
                    $next = false;
                    if (count($this->response->alerts)) {
                        Helper::setSession($this->response->alerts, 'alerts');
                    }
                }

                Helper::http('refresh', [
                    'url' => (is_array($this->response->redirect) ? $this->response->redirect[0] : $this->response->redirect),
                    'second' => $second
                ]);
            }

            if ($next) {
                $this->view(
                    '_base/error',
                    $this->response->arguments,
                    'error'
                );
            }
        }

        if (
            $this->log and
            (Helper::config('settings.log') or
                (!Helper::config('settings.log') and $this->response->statusCode !== 200)
            )
        ) {

            $this->action->middleware = implode(',', $this->action->middleware);

            foreach ($this->action as $key => $val)
                if ($val === '') $this->action->{$key} = null;

            (new Log())->add([
                'request'       => $this->request,
                'response'      => $this->response,
                'action'        => $this->action
            ]);
        }
    }


    /**
     *
     * View Page 
     * @param string|null  file          view file name
     * @param array   arguments     needed view variables 
     * @param string  layout        page structure indicator
     * @return this
     **/

    public function view($file = null, $arguments = [], $layout = 'default')
    {

        /**
         * 
         * Send HTTP status code.
         **/

        Helper::http($this->response->statusCode);

        if (is_null($file)) {

            /**
             * for API or Fetch/XHR output 
             **/

            if (isset($arguments['alerts']) === false and count($this->response->alerts)) {
                $arguments['alerts'] = Helper::sessionStoredAlert($this->response->alerts);
            }
            Helper::http('content_type', ['content' => 'json', 'write' => json_encode($arguments)]);
        } else {

            /**
             * 
             * Arguments are extracted and the title is defined.
             **/
            $view = true;
            // View Cache Get
            if (
                (strpos($this->request->uri, '/management') === false and strpos($this->request->uri, '/auth') === false) and
                Helper::config('settings.view_cache')
            ) {

                $cacheHash = md5($file . json_encode($arguments) . $layout);
                if (
                    file_exists($cacheFile = Helper::path('app/Storage/view_cache/' . $cacheHash . '.html')) and
                    strtotime(date('Y-m-d H:i:s +10 minutes', filemtime($cacheFile))) < time()
                ) {
                    $view = false;
                    echo file_get_contents($cacheFile);
                }
            }

            if ($view) {

                $arguments['title'] = isset($arguments['title']) !== false ?
                    str_replace(
                        ['[TITLE]', '[SEPERATOR]', '[APP]'],
                        [
                            $arguments['title'],
                            Helper::config('settings.separator'),
                            Helper::config('settings.name'),
                        ],
                        Helper::config('app.title_format')
                    )
                    : Helper::config('settings.name');

                extract($arguments);

                if (isset($description) === false) {
                    $description = @json_decode(Helper::config('settings.description'), true);
                    if (!$description) $description = Helper::config('settings.description');
                    else $description = $description[Helper::lang('lang.code')];
                }


                /**
                 * 
                 * Prepare the page structure according to the format.
                 **/

                $layoutFile = Helper::path('app/External/view_layouts.php');
                if (file_exists($layoutFile) === false) {
                    throw new \Exception((string) Helper::lang('error.layout_file_not_found') . ' ' . $layoutFile);
                }

                $layoutVars = require $layoutFile;

                if (isset($layoutVars[$layout]) !== false) {
                    $layoutVars = $layoutVars[$layout];
                } else {
                    $layoutVars = $layoutVars['app'];
                }

                if (isset($layoutVars['layout']) !== false) {
                    $layout = $layoutVars['layout'];
                }

                $Helper = Helper::class;

                foreach ($layoutVars['parts'] as $part) {

                    if ($part === '_')
                        $part = strpos((string)$file, '.') !== false ? str_replace('.', '/', $file) : $file;
                    else
                        $part = '_parts/' . $part;

                    if (file_exists($req = Helper::path('app/View/' . $part . '.php'))) {
                        require $req;
                    }
                }

                // View Cache Set
                if (
                    (strpos($this->request->uri, '/management') === false and strpos($this->request->uri, '/auth') === false) and
                    Helper::config('settings.view_cache')
                ) {

                    if (!is_dir($dir = Helper::path('app/Storage'))) mkdir($dir);
                    if (!is_dir($dir = Helper::path('app/Storage/view_cache'))) mkdir($dir);

                    file_put_contents($cacheFile, ob_get_contents());
                }
            }
        }
    }

    /**
     *  URL Generator
     *  @param string $route
     *  @return string $url
     **/
    public function url($route)
    {

        return Helper::base($route);
    }

    /**
     *  Clear Empty Files
     *  @param array $files
     *  @return array $files
     **/
    public function filterUploadedFiles($files)
    {


        foreach ($files as $name => $val) {

            if (isset($val['error']) !== false) {

                if ($val['error'] === 4) {
                    unset($files[$name]);
                }
            } else {

                $files[$name] = $this->filterUploadedFiles($files[$name]);
                if (is_null($files[$name]))
                    unset($files[$name]);
            }
        }

        if (!count($files)) {
            $files = null;
        }

        return $files;
    }

    /**
     *  Dynamic URL Generator
     *  @param string $route
     *  @param array $attributes
     *  @return string $url
     **/
    public function dynamicUrl($route, $param = [])
    {

        return Helper::base(Helper::dynamicURL($route, $param));
    }


    /**
     * Returns the active class if the given link is the current link.
     * @param string $link     given link
     * @param string $class    html class to return
     * @param boolean $exact   it gives full return when it is exactly the same.
     * @return string $string
     **/
    public function currentLink($link, $class = 'active', $exact = true)
    {

        $return = '';
        if (
            $this->request->uri === $link or
            (!$exact and strpos($this->request->uri, $link) !== false)
        ) {
            $return = ' ' . trim($class);
        }
        return $return;
    }

    /**
     *  authority
     *  @param string $route
     **/
    public function authority($route)
    {

        return (new \KX\Middleware\Auth($this))
            ->authority($route);
    }
}
