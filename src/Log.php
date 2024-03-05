<?php

/**
 * @package KX\Core
 * @subpackage Log
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Core\Request;
use KX\Core\Response;

final class Log
{

    /**
     * Save the log
     * @param object $request
     * @param object $response
     * @return void
     */
    public function save(Request $request, Response $response): void
    {

        // log model class check
        if (!class_exists('KX\Model\Logs')) {

            $log = [
                'request' => [
                    'date' => date('Y-m-d H:i:s'),
                    'method' => $request->getRequestMethod(),
                    'uri' => $request->getUri(),
                    'query_string' => $request->getQueryString(),
                    'header' => $request->getHeader(),
                    'get_params' => $request->getGetParams(),
                    'post_params' => $request->getPostParams(),
                    'middleware_params' => $request->getMiddlewareParams()
                ],
                'response' => [
                    'status_code' => $response->getStatusCode(),
                    'body' => $response->getBody(),
                    'redirection' => $response->getRedirection() ?
                        $response->getRedirection()['url']
                        : null,
                    'execution_time' => number_format(microtime(true) - KX_START, 4),
                ],
            ];

            $log = json_encode($log, JSON_PRETTY_PRINT) . ',';

            $logFile = KX_ROOT . 'app/Storage/logs/' . $response->getStatusCode() . '_' .
                date('Ymd') . '_' . Helper::getIp() . '.log';

            Helper::path('app/Storage/logs', true);

            if (!file_exists($logFile)) {
                touch($logFile);
            } else {
                $log = PHP_EOL . $log;
            }

            file_put_contents($logFile, $log, FILE_APPEND);
        } else {
            $logModel = new \KX\Model\Logs();
            $logModel->insert([
                'endpoint' => $request->getUri(),
                'status_code' => $response->getStatusCode(),
                'auth_token' => Helper::authToken(),
                'ip' => Helper::getIp(),
                'header' => Helper::getUserAgent(),
                'exec_time' => number_format(microtime(true) - KX_START, 4),
            ]);
        }
    }
}
