<?php

/**
 * @package KX\Core
 * @subpackage Response
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Core\Helper;

final class Response
{
    private $statusCode;
    private $responseMessage;
    private $responseHeaders;
    private $responseBody;
    private $layout;
    private $responseMessageList = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        206 => 'Partial Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        409 => 'Conflict',
        410 => 'Gone',
        412 => 'Precondition Failed',
        415 => 'Unsupported Media Type',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        503 => 'Service Unavailable'
    ];
    private $redirection = null;

    /**
     * Response constructor
     * @return object
     */
    public function __construct()
    {

        ob_start();
        $this->statusCode = 200;
        $this->responseMessage = 'OK';
        $this->responseHeaders = [];
        $this->responseBody = '';

        return $this;
    }

    /**
     * Set response code
     * @param int $code
     * @return object
     */
    public function setStatus(int $code): object
    {
        $this->statusCode = $code;
        $this->responseMessage = $this->responseMessageList[$code];
        return $this;
    }

    /**
     * Set response header
     * @param string $header
     * @return object
     */
    public function setHeader(string $header): object
    {
        $this->responseHeaders[] = $header;
        return $this;
    }

    /**
     * Apply headers
     * @return object
     */
    public function applyHeaders(): object
    {
        array_unshift(
            $this->responseHeaders,
            $_SERVER['SERVER_PROTOCOL'] .
                ' ' . $this->statusCode .
                ' ' . $this->responseMessage
        );
        foreach ($this->responseHeaders as $header) {
            header($header);
        }

        if ($this->redirection) {
            $this->runRedirection();
        }
        return $this;
    }

    /**
     * Set response body
     * @param string $body
     * @return object
     */
    public function setBody(string $body): object
    {
        $this->responseBody .= $body;
        return $this;
    }

    /**
     * Send response
     * @param string $body
     * @return object
     */
    public function send(string $body = ''): object
    {
        if (!empty($body)) {
            $this->setBody($body);
        }

        $this->applyHeaders();

        http_response_code($this->statusCode);

        echo $this->responseBody;

        return $this;
    }

    /**
     * Set layout
     * @param string|array $layout
     * @return object
     */
    public function setLayout(string|array $layout): object
    {
        if (is_array($layout)) {
            $this->layout = $layout;
        } else {
            $layoutPath = Helper::path('app/View' . DIRECTORY_SEPARATOR . '_' . $layout . '.php');
            if (!file_exists($layoutPath)) {
                throw new \Exception('Layout cannot be empty - ' . $layoutPath);
            }
            $this->layout = require($layoutPath);
        }
        return $this;
    }

    /**
     * Render view
     * @param string $view
     * @param array $data
     * @param string|array $layout
     * @return object
     */
    public function render(string $view, array $data = [], $layout = null): object
    {

        if (!empty($layout)) {
            $this->setLayout($layout);
        }

        if (!empty($this->layout)) {
            extract($data);
        }

        // default data
        $data['Helper'] = new Helper();
        if (isset($data['title']) === false) {
            $data['title'] = '';
        }
        if (!empty($data['title']) &&  Helper::config('APPLY_APP_NAME_TO_TITLE', true) && defined('APP_NAME')) {
            $data['title'] = $data['title'] . ' ' . Helper::config('APP_NAME_SEPARATOR') . ' ' . APP_NAME;
        }


        if (isset($data['description']) === false) {
            $data['description'] = '';
        }

        $viewPath = Helper::path('app/View' . DIRECTORY_SEPARATOR . str_replace(
            '.',
            DIRECTORY_SEPARATOR,
            $view
        ) . '.php');

        if (!file_exists($viewPath)) {
            throw new \Exception('View file not found - ' . $viewPath);
        } else {
            extract($data);
            if (empty($this->layout)) {
                require($viewPath);
            } else {
                if (isset($this->layout['variables']) !== false) {
                    $layout = $this->layout['variables'];
                }

                if (isset($_SERVER['HTTP_X_PJAX'])) {
                    echo '<title>' . $data['title'] . '</title>';
                    $this->layout['schema'] = ['x'];
                }

                foreach ($this->layout['schema'] as $part) {
                    if ($part === 'x') {
                        require($viewPath);
                    } else {
                        $partPath = Helper::path('app/View' . DIRECTORY_SEPARATOR . str_replace(
                            '.',
                            DIRECTORY_SEPARATOR,
                            $part
                        ) . '.php');
                        if (!file_exists($partPath)) {
                            throw new \Exception('View file not found - ' . $partPath);
                        }
                        require($partPath);
                    }
                }
            }
            $this->setBody(ob_get_clean());
        }

        $this->send();

        return $this;
    }

    /**
     * Render json
     * @param array $data
     * @return object
     */
    public function json(array $data): object
    {
        $this->setHeader('Content-Type: application/json; charset=utf-8');
        $this->send(json_encode($data));

        return $this;
    }

    /**
     * Redirect
     * @param string $url
     * @param int $statusCode
     * @param int $seconds
     * @return object
     */
    public function redirect(string $url, int $statusCode = 302, $seconds = 0): object
    {
        $this->redirection = [
            'url' => $url,
            'statusCode' => $statusCode,
            'seconds' => $seconds
        ];
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Get status code
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get body
     * @return string
     */
    public function getBody(): string
    {
        return $this->responseBody;
    }

    /**
     * Get redirection
     * @return array|null
     */
    public function getRedirection(): array|null
    {
        return $this->redirection;
    }

    /**
     * Run redirection
     * @return object
     */
    public function runRedirection(): object
    {
        if ($this->redirection) {
            if ($this->redirection['seconds'] > 0) {
                header('Refresh: ' . $this->redirection['seconds'] . '; url=' . $this->redirection['url']);
            } else {
                header('Location: ' . $this->redirection['url'], true, $this->redirection['statusCode']);
                exit;
            }
        }
        $this->redirection = null;
        return $this;
    }
}
