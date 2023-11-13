<?php
/**
 * @package KX
 * @subpackage Core\Log
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Model\Logs as Model;
use KX\Core\Helper;

final class Log {

    public function add($args) {

        $args = Helper::privateDataCleaner($args);
        $args = json_decode(json_encode($args));
        $exec = microtime(true) - KX_START;

        $request = json_encode($args->request);
        $response = json_encode($args->response);

        if (is_string($request) AND strlen($request) > 2000) { // Too long for log
            unset($args->request->params);
            $request = json_encode($args->request);
        }

        if (is_string($response) AND strlen($response) > 2000) { // Too long for log
            unset($args->response->arguments);
            $response = json_encode($args->response);
        }

        $model = new Model();
        return $model->insert([
            'endpoint'      => $args->request->uri,
            'method'        => $args->request->method,
            'middleware'    => $args->action->middleware,
            'controller'    => $args->action->controller,
            'http_status'   => (string) $args->response->statusCode,
            'auth_code'     => Helper::authCode(),
            'ip'            => Helper::getIp(),
            'header'        => Helper::getHeader(),
            'request'       => $request,
            'response'      => $response,
            'exec_time'     => (string) $exec
        ]);

    }

}