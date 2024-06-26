<?php

/**
 * @package KX\Core
 * @subpackage Helper
 */

declare(strict_types=1);

namespace KX\Core;

use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use \FilesystemIterator;

class Helper
{

    /**
     * Dump Data
     * @param any $value
     * @param boolean $exit
     * @return void
     */
    public static function dump($value, $exit = false)
    {

        echo '<pre>';
        var_dump($value);
        echo '</pre>' . PHP_EOL;

        if ($exit) exit;
    }


    /**
     * Path
     * @return string $path    main path
     */
    public static function path($dir = null, $createDir = false)
    {

        $path = defined('KX_ROOT') ? KX_ROOT : $_SERVER['DOCUMENT_ROOT'] . '/';
        if ($dir) {

            $dir = trim($dir, '/');
            $dir = explode('/', $dir);
            foreach ($dir as $folder) {

                // is file
                if (strpos($folder, '.') !== false) {
                    $path .= $folder;
                    break;
                }

                $path .= $folder . '/';
                if ($createDir && !is_dir($path)) {

                    mkdir($path, 0755);
                }
            }
        }

        return $path;
    }


    /**
     * Get the directory size
     * @param  string $directory
     * @return integer
     */
    public static function dirSize($directory)
    {
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->getFilename() == '..' or $file->getFilename() == '.gitignore')
                continue;

            $size += $file->getSize();
        }
        return $size;
    }

    /**
     * Base URL
     * @param  string|null $body
     * @return string $return
     */
    public static function base($body = null)
    {

        $url = (self::config('SSL', true) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/';
        if ($body) $url .= trim(strip_tags($body), '/');
        return $url;
    }


    /**
     * Generate URL
     * @param  string|null $body
     * @return string $return
     */
    public static function generateURL($module, $parameter = null)
    {

        $body = '';
        if (strpos($module, '_') !== false) {

            // $menuController = (new \KX\Controller\MenuController((object)['request' => '']));
            $module = explode('_', $module, 2);
            // $body = $menuController->urlGenerator($module[0], $module[1], $parameter);
        }

        return self::base($body);
    }

    /**
     *  Dynamic URL Generator
     *  @param string $route
     *  @param array $attributes
     *  @return string $url
     **/
    public static function dynamicURL($route, $param = [])
    {

        foreach ($param as $attr => $value) {
            $route = str_replace(':' . $attr, $value, $route);
        }
        return $route;
    }


    /**
     * Configuration Parameters
     * @param  string 	$setting setting value name
     * @param  boolean 	$format  return as formatted
     * @return any    	$return  setting value
     */
    public static function config($setting, $format = false)
    {

        global $kxConfigs, $kxLang;

        $return = false;
        $settings = false;
        $setting = trim($setting, '.');

        if (strpos($setting, '.') !== false) {

            $setting = explode('.', $setting, 2);

            // config files
            if (isset($configs[$setting[0]]) !== false) {

                $settings = $kxConfigs[$setting[1]];
            } else {

                $file = self::path('app/Config/' . $setting[0] . '.php');
                if (file_exists($file)) {

                    $settings = require $file;
                    $configs[$setting[1]] = $settings;
                }
            }

            if ($settings) {

                $setting = strpos($setting[1], '.') !== false ? explode('.', $setting[1]) : [$setting[1]];

                $data = null;
                foreach ($setting as $key) {

                    if (isset($settings[$key]) !== false) {
                        $data = $settings[$key];
                        $settings = $settings[$key];
                    } else {
                        $data = null;
                    }
                }
                $return = $data;
            }
        } else {
            // environment variables
            $return = isset($_ENV['KX_' . $setting]) !== false ?
                $_ENV['KX_' . $setting] :
                null;
        }

        $return =
            is_string($return) ? html_entity_decode($return) : $return;

        if ($format) {
            switch ($return) {
                case "true":
                    $return = true;
                    break;

                case "false":
                    $return = false;
                    break;

                case "null":
                    $return = null;
                    break;

                default:
                    if (is_numeric($return)) {
                        $return = (float) $return;
                    } elseif (is_string($return)) {
                        try {
                            $_return = json_decode($return);
                            if ($_return) {
                                $return = $_return;
                                if (isset($return->{$kxLang}) !== false) {
                                    $return = $return->{$kxLang};
                                }
                            }
                        } catch (\Exception $e) {
                            $return = $return;
                        }
                    }
            }
        }

        return $return;
    }

    /**
     * Returns Multi-dimensional Form Input Data
     * @param  array $extract    -> variable name => format parameter
     * @param  array $parameter  -> POST, GET or any input resource
     * @return array $return 
     */
    public static function input($extract, $from): array
    {
        $return = [];
        if (is_array($extract) && is_array($from)) {
            foreach ($extract as $key => $value) {
                if (isset($from[$key]) !== false) $return[$key] = self::filter($from[$key], $value);
                else $return[$key] = self::filter(null, $value);
            }
        }
        return $return;
    }


    /**
     * Filter Value
     * @param  any $data
     * @param  string $parameter 
     * @return any $return 
     */
    public static function filter($data = null, $parameter = 'text')
    {
        /**
         *  Available Parameters
         *  
         *     html             ->  trim + htmlspecialchars
         *     nulled_html      ->  trim + htmlspecialchars + if empty string, save as null
         *     check            ->  if empty, save as "off", not "on"
         *     check_as_boolean ->  if empty, save as false, not true
         *     int              ->  convert to integer value (int)
         *     nulled_int       ->  convert to integer value (int) if value is 0 then convert to null
         *     float            ->  convert to float value (floatval())
         *     password         ->  trim + password_hash
         *     nulled_password  ->  trim + password_hash, assign null if empty string
         *     date             ->  strtotime ~ input 12.00(mid day)
         *     nulled_text      ->  strip_tags + trim + htmlentities + if empty string, save as null
         *     nulled_email     ->  strip_tags + trim + filter_var@FILTER_VALIDATE_EMAIL + if empty string, save as null
         *     slug             ->  strip_tags + trim + slugGenerator
         *     text (default)   ->  strip_tags + trim + htmlentities
         *     script           ->  preg_replace for script tags
         *     color            ->  regex hex
         **/
        if (is_array($data)) {
            $_data = [];
            foreach ($data as $key => $value) {
                $_data[$key] = self::filter($value, $parameter);
            }
            $data = $_data;
        } else {

            $nulled = false;
            if (strpos($parameter, 'nulled_') !== false) {
                $nulled = true;
                $parameter = str_replace('nulled_', '', $parameter);
            }

            switch ($parameter) {

                case 'html':
                    $data = htmlspecialchars(trim((string)$data));
                    if ($nulled) {
                        $data = trim(strip_tags(htmlspecialchars_decode((string)$data)));
                    }
                    break;

                case 'check':
                case 'check_as_boolean':
                    $nulled = false;
                    $data = ($data) ? 'on' : 'off';
                    if ($parameter === 'check_as_boolean') {
                        $data = $data === 'on' ? true : false;
                    }
                    break;

                case 'int':
                    $data = (int)$data;
                    break;

                case 'float':
                    $data = (float)$data;
                    break;

                case 'password':
                    $data = password_hash(trim((string)$data), PASSWORD_DEFAULT);
                    break;

                case 'date':
                    $data = strtotime($data . ' 12:00');
                    break;

                case 'email':
                    $data = filter_var($data, FILTER_VALIDATE_EMAIL) ? $data : '';
                    break;

                case 'slug':
                    $data = empty($data) ? null : self::slugGenerator(strip_tags(trim((string)$data)));
                    break;

                case 'script':
                    $data = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '-', (string)$data);
                    break;

                case 'color':
                    if (!preg_match('/^#[a-f0-9]{6}$/i', (string)$data)) {
                        $data = '#000000';
                    }
                    break;

                default:
                    $data = strip_tags(
                        htmlspecialchars((string)$data)
                    );
                    break;
            }

            if ($nulled) {
                $data = empty($data) ? null : $data;
            }
        }

        return $data;
    }

    /**
     * Validation
     * @param  array $ruleSet
     * @param  object|null $response
     * @return array|object $return
     */
    public static function validation($ruleSet, $response = null): array|object
    {

        $return = [];
        foreach ($ruleSet as $name => $ruleDetails) {

            $return[$name] = [];

            $value = $ruleDetails['value'];
            $rule = $ruleDetails['pattern'];
            $rule = explode('|', $rule);
            foreach ($rule as $pattern) {

                $pattern = explode(':', $pattern);
                $rule = $pattern[0];
                $param = isset($pattern[1]) !== false ? $pattern[1] : null;

                switch ($rule) {

                    case 'required':
                        if (empty($value)) {
                            $return[$name][] = self::lang('form.required_validation');
                        }
                        break;

                    case 'min':
                        if (strlen((string)$value) < $param) {
                            $return[$name][] = self::lang('form.min_validation', ['min' => $param]);
                        }
                        break;

                    case 'max':
                        if (strlen((string)$value) > $param) {
                            $return[$name][] = self::lang('form.max_validation', ['max' => $param]);
                        }
                        break;

                    case 'email':
                        if (!filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
                            $return[$name][] = self::lang('form.email_validation');
                        }
                        break;

                    case 'url':
                        if (!filter_var((string)$value, FILTER_VALIDATE_URL)) {
                            $return[$name][] = self::lang('form.url_validation');
                        }
                        break;

                    case 'ip':
                        if (!filter_var((string)$value, FILTER_VALIDATE_IP)) {
                            $return[$name][] = self::lang('form.ip_validation');
                        }
                        break;

                    case 'regex':
                        if (!preg_match($param, (string)$value)) {
                            $return[$name][] = self::lang('form.regex_validation');
                        }
                        break;

                    case 'numeric':
                        if (!is_numeric($value)) {
                            $return[$name][] = self::lang('form.numeric_validation');
                        }
                        break;

                    case 'alpha':
                        // latin-ext supported 
                        if (!ctype_alpha(self::slugGenerator($value))) {
                            $return[$name][] = self::lang('form.alpha_validation');
                        }
                        break;

                    case 'alphanumeric':
                        if (!ctype_alnum(self::slugGenerator($value))) {
                            $return[$name][] = self::lang('form.alphanumeric_validation');
                        }
                        break;

                    case 'match':
                        if ($value !== $param) {
                            $return[$name][] = self::lang('form.match_validation', ['match' => $param]);
                        }
                        break;

                    case 'in':
                        if (!in_array($value, explode(',', $param))) {
                            $return[$name][] = self::lang('form.in_validation', ['in' => $param]);
                        }
                        break;

                    case 'not_in':
                        if (in_array($value, explode(',', $param))) {
                            $return[$name][] = self::lang('form.not_in_validation', ['not_in' => $param]);
                        }
                        break;
                }
            }

            if (empty($return[$name])) {
                unset($return[$name]);
            }
        }

        if (!is_null($response) && !empty($return)) {
            $r = [
                'status' => false,
                'notify' => [
                    [
                        'type' => 'error',
                        'message' => self::lang('form.fill_all_fields')
                    ]
                ],
                'dom' => []
            ];
            foreach ($return as $field => $messages) {
                $r['dom']['[name="' . $field . '"]'] = [
                    'addClass' => 'is-invalid',
                ];

                $r['dom']['[name="' . $field . '"] ~ .invalid-feedback'] = [
                    'text' => implode(' ', $messages)
                ];
            }
            $response->json($r);
            exit;
        }

        return $return;
    }


    /**
     * Create Header
     * @param  int|string $code    http status code or different header definitions: powered_by, location, refresh and content_type
     * @param  array $parameters   other parameters are sent as an array. 
     * available keys: write(echo), url(redirect url), second (redirect second), content(content-type)
     * @return void 
     */
    public static function http($code = 200, $parameters = [])
    {

        /* reference
				$parameters = [
						'write' => '',
						'url' => '',
						'second' => '',
						'content'  => ''
				]; */

        $httpCodes = [
            200 => 'OK',
            301 => 'Moved Permanently',
            302 => 'Found',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable'
        ];

        if (is_numeric($code) && isset($httpCodes[(int)$code]) !== false) {

            header($_SERVER["SERVER_PROTOCOL"] . ' ' . $code . ' ' . $httpCodes[(int) $code]);
        } else {

            switch ($code) {

                case 'powered_by':
                    header('X-Powered-By: KalipsoX/v' . KX_CORE_VERSION);
                    break;

                case 'retry_after':
                    header('Retry-After: ' . (isset($parameters['second']) !== false ? $parameters['second'] : 5));
                    break;

                case 'location':
                case 'refresh':
                    if (isset($parameters['url']) === false) {
                        $redirectUrl = isset($_SERVER['HTTP_REFERER']) !== false ? $_SERVER['HTTP_REFERER'] : self::base();
                    } else {
                        $redirectUrl = $parameters['url'];
                    }

                    if (isset($parameters['second']) === false or (!$parameters['second'] or !is_numeric($parameters['second']))) {
                        header('location: ' . $redirectUrl);
                    } else {
                        header('refresh: ' . $parameters['second'] . '; url=' . $redirectUrl);
                    }

                    break;

                case 'content_type':
                    if (isset($parameters['content']) === false) $parameters['content'] = null;

                    switch ($parameters['content']) {
                        case 'application/json':
                        case 'json':
                            $parameters['content'] = 'application/json';
                            break;

                        case 'application/javascript':
                        case 'js':
                            $parameters['content'] = 'application/javascript';
                            break;

                        case 'application/zip':
                        case 'zip':
                            $parameters['content'] = 'application/zip';
                            break;

                        case 'text/plain':
                        case 'txt':
                            $parameters['content'] = 'text/plain';
                            break;

                        case 'text/xml':
                        case 'xml':
                            $parameters['content'] = 'application/xml';
                            break;

                        case 'vcf':
                            $parameters['content'] = 'text/x-vcard';
                            break;

                        default:
                            $parameters['content'] = 'text/html';
                    }
                    header('Content-Type: ' . $parameters['content'] . '; Charset=' . self::config('app.charset'));
                    break;
            }
        }

        if (isset($parameters['write']) !== false) {
            echo $parameters['write'];
        }
    }

    /**
     * Language Translation
     * @param string $key 
     * @param array $parameters
     * @return string $key translated string    
     */
    public static function lang(string $key, $parameters = []): string
    {

        global
            $kxLangParameters, $kxLang;

        if (!is_array($kxLangParameters) && file_exists(self::path('app/Localization/' . $kxLang . '.php')) !== false) {
            $kxLangParameters = require self::path('app/Localization/' . $kxLang . '.php');
        }


        $key = strpos($key, '.') !== false ? explode('.', $key) : [$key];

        $terms = $kxLangParameters;
        foreach ($key as $index) {
            if (isset($terms[$index]) !== false) {
                $terms = $terms[$index];
                $key = $terms;
            }
        }

        if (is_array($key)) {
            $key = $index;
        }

        if (is_array($parameters) && count($parameters) > 0) {
            foreach ($parameters as $k => $value) {
                $k = ':' . $k;
                $key = str_replace($k, $value, $key);
            }
        }

        return $key;
    }

    /**
     * Assets File Controller
     * @param string $filename
     * @param bool $version
     * @param bool $tag
     * @param bool $echo
     * @param array $externalParameters
     * @return string|null
     */
    public static function assets(string $filename, $version = true, $tag = false, $echo = false, $externalParameters = [])
    {

        $fileDir = rtrim(self::path() . 'assets/' . $filename, '/');
        $return = trim(self::base() . 'assets/' . $filename, '/');
        if (file_exists($fileDir)) {

            $return = $version == true ? $return . '?v=' . filemtime($fileDir) : $return;
            if ($tag == true) // Only support for javascript and stylesheet files
            {
                $_externalParameters = '';
                foreach ($externalParameters as $param => $val) {
                    $_externalParameters = ' ' . $param . '="' . $val . '"';
                }

                $file_data = pathinfo($fileDir);
                if ($file_data['extension'] == 'css') {
                    $return = '<link' . $_externalParameters . ' rel="stylesheet" href="' . $return . '" type="text/css"/>' . PHP_EOL . '       ';
                } elseif ($file_data['extension'] == 'js') {
                    $return = '<script' . $_externalParameters . ' src="' . $return . '"></script>' . PHP_EOL . '       ';
                }
            }
        } else {
            $return = null;
        }

        if ($echo == true) {

            echo $return;
            return null;
        } else {
            return $return;
        }
    }

    /**
     * CSRF Token Generator
     * @param bool $onlyToken  output option
     * @return string|null
     */
    public static function createCSRF($onlyToken = false)
    {


        $return = null;
        if (isset($_COOKIE[self::config('SESSION_NAME')]) !== false) {

            $csrf = [
                'timeout'       => strtotime('+1 hour'),
                'header'        => self::getUserAgent(),
                'ip'            => self::getIp()
            ];

            $return = self::encryptKey(json_encode($csrf));

            if (!$onlyToken) {
                $return = '<input type="hidden" name="_token" value="' . $return . '">';
            }
        }
        return $return;
    }

    /**
     * CSRF Token Verifier
     * @param string $token  Token
     * @return bool
     */
    public static function verifyCSRF($token)
    {

        $return = false;
        $token = @json_decode(self::decryptKey($token), true);
        if (is_array($token)) {

            if (
                (isset($token['cookie']) !== false && $token['cookie'] == $_COOKIE[self::config('SESSION_NAME')]) &&
                (isset($token['timeout']) !== false && $token['timeout'] >= time()) &&
                (isset($token['header']) !== false && $token['header'] == self::getUserAgent()) &&
                (isset($token['ip']) !== false && $token['ip'] == self::getIp())

            ) {
                $return = true;
            }
        }

        return $return;
    }

    /**
     * Current Page Class
     * @param string $route  Route
     * @param string $class class
     * @param bool $returnAsBool return as boolean
     * @return string|bool
     */
    public static function currentPage($route = '', $class = ' active', $returnAsBool = false): string|bool
    {

        global $kxRequestUri;

        $req = trim($kxRequestUri, '/');
        $route = trim($route, '/');

        if ($req === $route) {
            return $returnAsBool ? true : $class;
        }
        return $returnAsBool ? false : '';
    }

    /**
     * Get Session
     * Return all session information or specific data.
     * @param string $key   specific key
     * @return bool|string|array|null
     */
    public static function getSession($key = null)
    {

        $return = null;
        if (is_string($key) && isset($_SESSION[$key]) !== false) {
            $return = $_SESSION[$key];
        } elseif (is_null($key)) {
            $return = $_SESSION;
        }
        return $return;
    }

    /**
     * Set Session
     * Set to all session information or specific data.
     * @param any $data   data
     * @param string $key   specific key
     * @return bool
     */
    public static function setSession($data = null, $key = null)
    {

        if (is_string($key)) {
            $_SESSION[$key] = $data;
        } else {

            if (isset($data->password) !== false) {
                unset($data->password);
            }

            $_SESSION['user'] = $data;
        }

        return $_SESSION;
    }

    /**
     * Clear Session
     * Clear session data
     * @param string $key  session key
     * @return void
     */
    public static function clearSession($key = null)
    {

        $key = is_null($key) ? 'user' : $key;
        if (isset($_SESSION[$key]) !== false) {
            unset($_SESSION[$key]);
        }
    }


    /**
     * Get IP Adress
     * @return string
     */
    public static function getIp()
    {

        if (getenv("HTTP_CLIENT_IP")) {
            $ip = getenv("HTTP_CLIENT_IP");
        } elseif (getenv("HTTP_X_FORWARDED_FOR")) {

            $ip = getenv("HTTP_X_FORWARDED_FOR");
            if (strpos($ip, ',')) {
                $tmp = explode(',', $ip);
                $ip = trim($tmp[0]);
            }
        } else {
            $ip = getenv("REMOTE_ADDR");
        }

        return $ip == '::1' ? '127.0.0.1' : $ip;
    }


    /**
     * Get User Agent
     * @return string
     */
    public static function getUserAgent()
    {

        return isset($_SERVER['HTTP_USER_AGENT']) !== false ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
    }


    /**
     * Format File Size
     * @param $bytes
     * @return string
     */

    public static function formatSize($bytes)
    {

        if ($bytes >= 1073741824) $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        elseif ($bytes >= 1048576) $bytes = number_format($bytes / 1048576, 2) . ' MB';
        elseif ($bytes >= 1024) $bytes = number_format($bytes / 1024, 2) . ' KB';
        elseif ($bytes > 1) $bytes = $bytes . ' ' . self::lang('base.byte') . self::lang('lang.plural_suffix');
        elseif ($bytes == 1) $bytes = $bytes . ' ' . self::lang('base.byte');
        else $bytes = '0 ' . self::lang('base.byte');

        return $bytes;
    }

    /**
     * Remove directory
     * @param $folder
     * @return bool
     */
    public static function removeDir($folder)
    {

        $d = new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
        $r = new RecursiveIteratorIterator($d, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($r as $file) {
            $file->isDir() ?  rmdir($file->getPathname()) : unlink($file->getPathname());
        }
    }


    /**
     * Get User Device Details
     * @param string|null $ua
     * @return array
     */

    public static function userAgentDetails($ua = null): array
    {

        $ua = is_null($ua) ? self::getUserAgent() : $ua;
        $browser = '';
        $platform = '';
        $bIcon = 'ti ti-circle-x';
        $pIcon = 'ti ti-circle-x';

        $browserList = [
            'Trident\/7.0'          => ['Internet Explorer 11', 'ti ti-browser'],
            'MSIE'                  => ['Internet Explorer', 'ti ti-browser'],
            'Edge'                  => ['Microsoft Edge', 'ti ti-brand-edge'],
            'Edg'                   => ['Microsoft Edge', 'ti ti-brand-edge'],
            'Internet Explorer'     => ['Internet Explorer', 'ti ti-browser'],
            'Beamrise'              => ['Beamrise', 'ti ti-planet'],
            'Opera'                 => ['Opera', 'ti ti-brand-opera'],
            'OPR'                   => ['Opera', 'ti ti-brand-opera'],
            'Vivaldi'               => ['Vivaldi', 'ti ti-planet'],
            'Shiira'                => ['Shiira', 'ti ti-planet'],
            'Chimera'               => ['Chimera', 'ti ti-planet'],
            'Phoenix'               => ['Phoenix', 'ti ti-planet'],
            'Firebird'              => ['Firebird', 'ti ti-planet'],
            'Camino'                => ['Camino', 'ti ti-planet'],
            'Netscape'              => ['Netscape', 'ti ti-planet'],
            'OmniWeb'               => ['OmniWeb', 'ti ti-planet'],
            'Konqueror'             => ['Konqueror', 'ti ti-planet'],
            'icab'                  => ['iCab', 'ti ti-planet'],
            'Lynx'                  => ['Lynx', 'ti ti-planet'],
            'Links'                 => ['Links', 'ti ti-planet'],
            'hotjava'               => ['HotJava', 'ti ti-planet'],
            'amaya'                 => ['Amaya', 'ti ti-planet'],
            'MiuiBrowser'           => ['MIUI Browser', 'ti ti-planet'],
            'IBrowse'               => ['IBrowse', 'ti ti-planet'],
            'iTunes'                => ['iTunes', 'ti ti-planet'],
            'Silk'                  => ['Silk', 'ti ti-planet'],
            'Dillo'                 => ['Dillo', 'ti ti-planet'],
            'Maxthon'               => ['Maxthon', 'ti ti-planet'],
            'Arora'                 => ['Arora', 'ti ti-planet'],
            'Galeon'                => ['Galeon', 'ti ti-planet'],
            'Iceape'                => ['Iceape', 'ti ti-planet'],
            'Iceweasel'             => ['Iceweasel', 'ti ti-planet'],
            'Midori'                => ['Midori', 'ti ti-planet'],
            'QupZilla'              => ['QupZilla', 'ti ti-planet'],
            'Namoroka'              => ['Namoroka', 'ti ti-planet'],
            'NetSurf'               => ['NetSurf', 'ti ti-planet'],
            'BOLT'                  => ['BOLT', 'ti ti-planet'],
            'EudoraWeb'             => ['EudoraWeb', 'ti ti-planet'],
            'shadowfox'             => ['ShadowFox', 'ti ti-planet'],
            'Swiftfox'              => ['Swiftfox', 'ti ti-planet'],
            'Uzbl'                  => ['Uzbl', 'ti ti-planet'],
            'UCBrowser'             => ['UCBrowser', 'ti ti-planet'],
            'Kindle'                => ['Kindle', 'ti ti-planet'],
            'wOSBrowser'            => ['wOSBrowser', 'ti ti-planet'],
            'Epiphany'              => ['Epiphany', 'ti ti-planet'],
            'SeaMonkey'             => ['SeaMonkey', 'ti ti-planet'],
            'Avant Browser'         => ['Avant Browser', 'ti ti-planet'],
            'Chrome'                => ['Google Chrome', 'ti ti-brand-chrome'],
            'CriOS'                 => ['Google Chrome', 'ti ti-brand-chrome'],
            'Safari'                => ['Safari', 'ti ti-brand-safari'],
            'Firefox'               => ['Firefox', 'ti ti-brand-firefox'],
            'Mozilla'               => ['Mozilla', 'ti ti-brand-firefox']
        ];

        $platformList = [
            'windows'               => ['Windows', 'ti ti-brand-windows'],
            'iPad'                  => ['iPad', 'ti ti-brand-apple'],
            'iPod'                  => ['iPod', 'ti ti-brand-apple'],
            'iPhone'                => ['iPhone', 'ti ti-brand-apple'],
            'mac'                   => ['Apple MacOS', 'ti ti-brand-apple'],
            'android'               => ['Android', 'ti ti-brand-android'],
            'linux'                 => ['Linux', 'ti ti-brand-open-source'],
            'Nokia'                 => ['Nokia', 'ti ti-brand-windows'],
            'BlackBerry'            => ['BlackBerry', 'ti ti-brand-open-source'],
            'FreeBSD'               => ['FreeBSD', 'ti ti-brand-open-source'],
            'OpenBSD'               => ['OpenBSD', 'ti ti-brand-open-source'],
            'NetBSD'                => ['NetBSD', 'ti ti-brand-open-source'],
            'UNIX'                  => ['UNIX', 'ti ti-brand-open-source'],
            'DragonFly'             => ['DragonFlyBSD', 'ti ti-brand-open-source'],
            'OpenSolaris'           => ['OpenSolaris', 'ti ti-brand-open-source'],
            'SunOS'                 => ['SunOS', 'ti ti-brand-open-source'],
            'OS\/2'                 => ['OS/2', 'ti ti-brand-open-source'],
            'BeOS'                  => ['BeOS', 'ti ti-brand-open-source'],
            'win'                   => ['Windows', 'ti ti-brand-windows'],
            'Dillo'                 => ['Linux', 'ti ti-brand-open-source'],
            'PalmOS'                => ['PalmOS', 'ti ti-brand-open-source'],
            'RebelMouse'            => ['RebelMouse', 'ti ti-brand-open-source']
        ];

        foreach ($browserList as $pattern => $name) {
            if (preg_match("/" . $pattern . "/i", $ua, $match)) {
                $bIcon = $name[1];
                $browser = $name[0];
                $known = ['Version', $pattern, 'other'];
                $patternVersion = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
                preg_match_all($patternVersion, $ua, $matches);
                $i = count($matches['browser']);
                if ($i != 1) {
                    if (strripos($ua, "Version") < strripos($ua, $pattern)) {
                        $version = @$matches['version'][0];
                    } else {
                        $version = @$matches['version'][1];
                    }
                } else {
                    $version = @$matches['version'][0];
                }
                break;
            }
        }

        foreach ($platformList as $key => $platform) {
            if (stripos($ua, $key) !== false) {
                $pIcon = $platform[1];
                $platform = $platform[0];
                break;
            }
        }

        $browser = $browser == '' ? self::lang('undetected') : $browser;
        $platform = $platform == '' ? self::lang('undetected') : $platform;

        $osPatterns = [
            '/windows nt 10/i'      =>  'Windows 10',
            '/windows nt 6.3/i'     =>  'Windows 8.1',
            '/windows nt 6.2/i'     =>  'Windows 8',
            '/windows nt 6.1/i'     =>  'Windows 7',
            '/windows nt 6.0/i'     =>  'Windows Vista',
            '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
            '/windows nt 5.1/i'     =>  'Windows XP',
            '/windows xp/i'         =>  'Windows XP',
            '/windows nt 5.0/i'     =>  'Windows 2000',
            '/windows me/i'         =>  'Windows ME',
            '/win98/i'              =>  'Windows 98',
            '/win95/i'              =>  'Windows 95',
            '/win16/i'              =>  'Windows 3.11',
            '/macintosh|mac os x/i' =>  'Mac OS X',
            '/mac_powerpc/i'        =>  'Mac OS 9',
            '/linux/i'              =>  'Linux',
            '/ubuntu/i'             =>  'Ubuntu',
            '/iphone/i'             =>  'iPhone',
            '/ipod/i'               =>  'iPod',
            '/ipad/i'               =>  'iPad',
            '/android/i'            =>  'Android',
            '/blackberry/i'         =>  'BlackBerry',
            '/webos/i'              =>  'Mobile'
        ];

        foreach ($osPatterns as $regex => $value) {
            if (preg_match($regex, $ua)) {
                $osPlatform = $value;
            }
        }

        $version = empty($version) ? '' : 'v' . $version;
        $osPlatform = isset($osPlatform) === false ? self::lang('undetected') : $osPlatform;

        return [
            'user_agent' => $ua,         // User Agent
            'browser'   => $browser,    // Browser Name
            'version'   => $version,    // Version
            'platform'  => $platform,   // Platform
            'os'        => $osPlatform, // Platform Detail
            'b_icon'    => $bIcon,      // Browser Icon(icon class name like from Material Design Icon)
            'p_icon'    => $pIcon       // Platform Icon(icon class name like from Material Design Icon)
        ];
    }


    /**
     * Get String to Slug
     * @param string $str
     * @param array $options
     * @return string
     */
    public static function slugGenerator($str, $options = []): string
    {

        $str = str_replace(['\'', '"'], '', html_entity_decode($str));
        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII;');
        $str = $transliterator->transliterate($str);

        $defaults = [
            'delimiter' => '-',
            'limit' => null,
            'lowercase' => true,
            'replacements' => [],
            'transliterate' => true
        ];
        $options = array_merge($defaults, $options);

        $str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);
        $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);
        $str = preg_replace('/[^A-Za-z0-9\-]/', '', $str);
        $str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);
        $str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');
        $str = trim($str, $options['delimiter']);
        return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
    }


    /**
     * String Transformator
     * @param string $type
     * @param string $data
     * @return string
     */
    public static function stringTransform($type, $data = ''): string
    {

        switch ($type) {

            case 'uppercasewords':
            case 'ucw':
                $data = \Transliterator::create("Any-Title")->transliterate($data);
                break;

            case 'uppercasefirst':
            case 'ucf':
                $data = \Transliterator::create("Any-Title")->transliterate($data);
                $data = explode(' ', $data);
                if (count($data) > 1) {

                    $_data = [0 => $data[0]];
                    foreach ($data as $index => $text) {

                        if ($index) {

                            $_data[$index] = self::stringTransform('l', $text);
                        }
                    }
                    $data = implode(' ', $_data);
                } else {
                    $data = implode(' ', $data);
                }
                break;

            case 'lowercase':
            case 'l':
                $data = \Transliterator::create("Any-Lower")->transliterate($data);
                break;

            case 'uppercase':
            case 'u':
                $data = \Transliterator::create("Any-Upper")->transliterate($data);
                break;
        }

        return $data;
    }


    /**
     * Data Encrypter
     * @param string $text
     * @return string
     */
    public static function encryptKey($text): string
    {

        $ciphering = "AES-128-CTR";
        $encryptionIv = '1234567891011121';
        $encryptionKey = md5((string) self::config('settings.name'));
        $text = openssl_encrypt((string)$text, $ciphering, $encryptionKey, 0, $encryptionIv);
        return bin2hex($text);
    }


    /**
     * Data Decrypter
     * @param string $encryptedString
     * @return string
     */
    public static function decryptKey($encryptedString): string
    {

        $ciphering = "AES-128-CTR";
        $decryptionIv = '1234567891011121';
        $decryptionKey = md5((string) self::config('settings.name'));
        return openssl_decrypt(hex2bin($encryptedString), $ciphering, $decryptionKey, 0, $decryptionIv);
    }

    /**
     * Write the value of the submitted field.
     * @param string $name
     * @param array|object $parameters
     * @param string $type  format parameter
     * @return string
     */
    public static function inputValue($name, $parameters, $type = '')
    {

        $return = '';

        $parameters = (array)$parameters;
        if (isset($parameters[$name]) !== false) {
            $return = $parameters[$name];

            if ($type == 'date' && !is_null($return)) {
                $return = date('Y-m-d', (int) $return);
            }

            $return = 'value="' . $return . '"';
        }
        return $return;
    }

    /**
     * Private data cleaner
     * @param array|object $data
     * @return array|object
     */
    public static function privateDataCleaner($data)
    {

        $return = is_object($data) ? (object)[] : [];
        foreach ($data as $k => $v) {

            if (is_array($v)) {
                $v = self::privateDataCleaner($v);
            } else {
                $v = in_array($k, ['password']) !== false ? '***' : $v;
            }

            if (is_object($return)) {
                $return->{$k} = $v;
            } else {
                $return[$k] = $v;
            }
        }
        return $return;
    }

    /**
     * Generate a Token
     * @param int $length
     * @return string
     */

    public static function tokenGenerator($length = 80): string
    {

        $key = '';
        list($usec, $sec) = explode(' ', microtime());
        $inputs = array_merge(range('z', 'a'), range(0, 9), range('A', 'Z'));
        for ($i = 0; $i < $length; $i++) {
            $key .= $inputs[mt_rand(0, (count($inputs) - 1))];
        }
        return $key;
    }

    /**
     * Clean given HTML tags from a string
     * @param string $data  full html string
     * @param array $ tags  given tags
     * @return string
     */
    public static function cleanHTML($data, $tags = [])
    {

        $reg = [];
        foreach ($tags as $tag) {

            if (in_array($tag, ['meta', 'hr', 'br']))
                $reg[] = '<' . $tag . '[^>]*>';

            else
                $reg[] = '<' . $tag . '[^>]*>.+?<\/' . $tag . '>';
        }

        $reg = implode('|', $reg);

        return preg_replace('/(' . $reg . ')/is', '', $data);
    }


    /**
     * UUID generator.
     * @return string
     */
    public static function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }


    /**
     * String shortener.
     * @param string text           long text
     * @param integer length        string length 
     * @param boolean withDots      export with 3 dots
     * @return string
     */
    public static function stringShortener($text, $length = 20, $withDots = true)
    {

        if (strlen($text) > $length) {
            if ($withDots) {
                $withDots = '...';
                $length = $length - 3;
            } else $withDots = '';

            if (function_exists("mb_substr")) $text = trim(mb_substr($text, 0, $length, "UTF-8")) . $withDots;
            else $text = trim(substr($text, 0, $length)) . $withDots;
        }

        return $text;
    }


    /**
     * Array flatter.
     * @param array array           bulk array
     * @param string mainKey        parent key
     * @return array
     */
    public static function arrayFlat($array, $mainKey = null)
    {
        if (!is_array($array)) {
            return false;
        }
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge(
                    $result,
                    self::arrayFlat($value, ($mainKey ? $mainKey . '.' : '') . $key)
                );
            } else {
                $result[($mainKey ? $mainKey . '.' : '') . $key] = $value;
            }
        }
        return $result;
    }

    /**
     * Load .env file
     * @return void
     */
    public static function loadConfig()
    {
        global $kxConfigs;

        $path = self::path('.env');
        if (file_exists($path)) {

            $envContent = file_get_contents($path);
            // regular expression to match each line
            $pattern = '/^\s*([\w.-]+)\s*=\s*(.*?)\s*(?:(?=#)|$)/m';

            // find all matches
            preg_match_all(
                $pattern,
                $envContent,
                $matches,
                PREG_SET_ORDER
            );

            $envArray = [];

            foreach ($matches as $match) {
                $envArray[$match[1]] = trim($match[2], "\" \t\n\r\0\x0B");
            }

            // set environment variables
            foreach ($envArray as $key => $value) {
                $_ENV['KX_' . $key] = $value;
            }
        }

        // config folder
        $kxConfigs = [];
        $configPath = self::path('Config');
        if (file_exists($configPath)) {

            $files = array_diff(scandir($configPath), ['.', '..']);
            foreach ($files as $file) {

                $fileName = explode('.', $file)[0];
                $kxConfigs[$fileName] = require $configPath . '/' . $file;
            }
        }
    }

    /**
     * Set JWT Secret
     * @param string $secret
     * @return void
     */
    public static function setJWTSecret($secret)
    {
        global $kxJwtSecret;
        $kxJwtSecret = $secret;
    }

    /**
     * Set Language
     * @param string $lang
     * @return void
     */
    public static function setLang($langKey)
    {
        global $kxLangParameters, $kxLang;

        if (file_exists(self::path('app/Localization/' . $langKey . '.php')) !== false) {
            $kxLangParameters = require self::path('app/Localization/' . $langKey . '.php');

            setlocale(LC_ALL, $kxLangParameters['lang']['iso_code'] . '.UTF-8', $kxLangParameters['lang']['iso_code'], $kxLangParameters['lang']['code']);
        }
        $kxLang = $langKey;
        $_SESSION['KX_LANG'] = $langKey;
    }

    public static function getClasses($path)
    {
        $classes = [];
        $namespace = 'KX\\' . str_replace(
            [
                'app/',
                '/'
            ],
            [
                '',
                '\\'
            ],
            $path
        );
        $files = array_diff(scandir(self::path($path)), ['.', '..']);
        foreach ($files as $file) {

            $fileName = explode('.', $file)[0];
            if (!empty($fileName)) {
                $classes[] = $namespace . '\\' . $fileName;
            }
        }

        return $classes;
    }

    /**
     * Get session data
     * @param string $section
     * @param string $key
     * @return mixed
     */
    public static function sessionData(string $section = null, string $key = null): mixed
    {
        global $kxSession;


        $return = null;
        if (is_null($section)) {
            $return = $kxSession;
        } else {
            $return = isset($kxSession->{$section}) !== false ? $kxSession->{$section} : null;

            if (!is_null($key)) {
                $return = isset($return->{$key}) !== false ? $return->{$key} : null;
            }
        }

        if ($section === 'user' && is_null($key) && !empty($return)) {
            $return->name = !empty(trim($return->f_name . ' ' . $return->l_name)) ? trim($return->f_name . ' ' . $return->l_name) : $return->u_name;
        }

        return $return;
    }

    /**
     * Authorization
     * @param string $endpoint
     * @return bool
     */
    public static function authorization(string $endpoint): bool
    {
        global $kxSession;

        $endpoint = str_replace(['/', '-'], ['.', '_'], trim($endpoint, '/'));

        $return = false;
        if (isset($kxSession->role) !== false && isset($kxSession->role->routes) !== false && in_array($endpoint, $kxSession->role->routes) !== false) {
            $return = true;
        }

        return $return;
    }

    /**
     * first letters
     * @param string $text
     * @return string
     */
    public static function firstLetters(string $text): string
    {
        $text = mb_strtoupper($text, 'UTF-8');
        $explode = explode(' ', $text);
        $firstChars = '';
        foreach ($explode as $v) {
            $firstChars .= mb_substr($v, 0, 1, 'UTF-8');
        }

        return $firstChars;
    }

    /**
     * Get auth token
     * @return string|null
     */
    public static function authToken(): ?string
    {

        if (self::config('AUTH_STRATEGY') === 'session') {
            $authToken = isset($_COOKIE[self::config('SESSION_NAME')]) !== false ?
                $_COOKIE[self::config('SESSION_NAME')] :
                null;
        } else {
            $authToken = isset($_SERVER['HTTP_AUTHORIZATION']) !== false ?
                $_SERVER['HTTP_AUTHORIZATION'] :
                null;

            if ($authToken) {
                $authToken = explode(' ', $authToken);
                $authToken = isset($authToken[1]) !== false ? $authToken[1] : null;
            }
        }

        return $authToken;
    }
}
