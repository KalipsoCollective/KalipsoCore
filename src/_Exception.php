<?php

/**
 * @package KX
 * @subpackage Core\Exception
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Core\Helper;

final class Exception
{

	/**
	 *  Fatal error handler
	 **/
	static function fatalHandler()
	{

		$error = error_get_last();
		if (!is_null($error) and is_array($error) and $error["type"] == E_ERROR) {
			self::errorHandler($error["type"], $error["message"], $error["file"], $error["line"]);
		}
	}

	/**
	 *  Error handler output
	 **/
	static function errorHandler($errNo, string $errMsg, string $file, int $line)
	{

		ob_get_clean();
		ob_start();

		$output = '';
		if (Helper::config('app.dev_mode')) {
			$output .= $file . ':' . $line . ' - ';
		}
		$output .= $errMsg;
		if ($errNo) {
			$output .= ' <strong>(' . $errNo . ')</strong>';
		}

		if (isset($_SERVER['HTTP_ACCEPT']) !== false and $_SERVER['HTTP_ACCEPT'] === 'application/json') {

			Helper::http('content_type', [
				'content' => 'json',
				'write' => json_encode([
					'error' => htmlspecialchars($output)
				])
			]);
		} else {

			$handlerInterface = '
			<!doctype html>
			<html>
				<head>
					<meta charset="utf-8">
					<title>Error Handler - KX</title>
					<style>
					body {
						font-family: "Courier New", monospace;
						background: #151515;
						color: #b9b9b9;
						padding: 1rem;
					}
					pre {
						font-family: "Courier New", monospace;
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
			echo str_replace('[OUTPUT]', $errorOutput, $handlerInterface);
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
}
