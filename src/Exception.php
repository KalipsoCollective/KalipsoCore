<?php

/**
 * @package KX\Core
 * @subpackage Exception
 */

declare(strict_types=1);

namespace KX\Core;

/**
 * Exception class
 *
 * @package KX
 * @subpackage Core\Exception
 */

use KX\Core\Request;
use KX\Core\Response;

final class Exception
{

    private static $customErrorHandler = null;

    private static $possibleHttpStatusCodes = [
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        503 => 'Service Unavailable'
    ];

    /**
     *  Fatal error handler
     **/
    public static function fatalHandler()
    {

        $error = error_get_last();
        if (!is_null($error) and is_array($error) and $error["type"] == E_ERROR) {
            self::errorHandler($error["type"], $error["message"], $error["file"], $error["line"]);
        }
    }

    /**
     *  Error handler output
     * @param int $errNo
     * @param string $errMsg
     * @param string $file
     * @param int $line
     * @return void
     **/
    public static function errorHandler($errNo, string $errMsg, string $file, int $line, $context = null)
    {

        ob_get_clean();
        ob_start();

        if (
            is_array(self::$customErrorHandler) &&
            count(self::$customErrorHandler) === 3 &&
            is_callable(self::$customErrorHandler[2])
        ) {

            call_user_func_array(
                self::$customErrorHandler[2],
                [
                    self::$customErrorHandler[0],
                    self::$customErrorHandler[1],
                    $errNo,
                    $errMsg,
                    $file,
                    $line,
                    $context
                ]
            );
            exit;
        }

        $output = '';
        if (Helper::config('DEV_MODE', true)) {
            $output .= $file . ':' . $line . ' - ';
        }
        $output .= $errMsg;
        if ($errNo) {
            $output .= ' <strong>(' . $errNo . ')</strong>';
        }
        // set http status code
        if (isset(self::$possibleHttpStatusCodes[$errNo]) !== false) {
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . $errNo . ' ' . self::$possibleHttpStatusCodes[$errNo], true, $errNo);
        } else {
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        }

        if (isset($_SERVER['HTTP_ACCEPT']) !== false and $_SERVER['HTTP_ACCEPT'] === 'application/json') {
            header('Content-Type: application/json, charset=utf-8');
            echo json_encode([
                'error' => htmlspecialchars($output)
            ]);
        } else {
            header('Content-Type: text/html, charset=utf-8');
            $handlerOutput = '
			<!doctype html>
			<html>
				<head>
					<meta charset="utf-8">
					<title>Error Handler - KX</title>
					<style>
					body {
						font-family: monospace;
						background: #151515;
						color: #b9b9b9;
						padding: 1rem;
					}
					pre {
						font-family: monospace;
					}
					h1 {
						margin: 0;
						color: #fff;
					}
					h2 {
						margin: 0;
						color: #434343;
					}
					</style>
				</head>
				<body>
					<h1>KalipsoX</h1>
					<h2>Error Handler</h2>
					<pre>[OUTPUT]</pre>
				</body>
			</html>';

            $errorOutput = '    ' . $output;
            echo str_replace('[OUTPUT]', $errorOutput, $handlerOutput);
        }
        exit;
    }

    /**
     *  Exception handler
     **/
    static function exceptionHandler($e = null)
    {

        if (is_null($e)) {

            die('Not handledable.');
        } else {

            self::errorHandler($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }

    /**
     *  Custom error handler
     **/
    public static function setErrorHandler(Request $request, Response $response, $callback = null)
    {

        if (is_callable($callback)) {

            self::$customErrorHandler = [$request, $response, $callback];
        } else {

            throw new \Exception('Custom error handler must be callable.');
        }
    }
}
