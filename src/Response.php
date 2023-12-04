<?php

/**
 * @package KX\Core
 * @subpackage Response
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Core\Helper;
use KX\Core\Exception;

final class Response
{
    private $response;
    private $statusCode;
    private $responseMessage;
    private $responseHeaders;
    private $responseBody;
    private $layout;

    /**
     * Response constructor
     * @return object
     */
    public function __construct()
    {
        $this->response = [];
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
    public function setStatusCode(int $code): object
    {
        $this->statusCode = $code;
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
     * Set response message
     * @param string $message
     * @return object
     */

    public function setResponseMessage(string $message): object
    {
        $this->responseMessage = $message;
        return $this;
    }

    /**
     * Apply headers
     * @return object
     */
    public function applyHeaders(): object
    {
        foreach ($this->responseHeaders as $header) {
            header($header);
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
        $this->responseBody = $body;
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
     * @return object
     */
    public function render(string $view, array $data = []): object
    {

        if (!empty($this->layout)) {
            extract($data);
        }

        // default data
        $data['Helper'] = new Helper();

        $viewPath = Helper::path('app/View' . DIRECTORY_SEPARATOR . str_replace(
            '.',
            DIRECTORY_SEPARATOR,
            $view
        ) . '.php');

        if (!file_exists($viewPath)) {
            throw new \Exception('View file not found - ' . $viewPath);
        } else {
            extract($data);
            ob_start();
            foreach ($this->layout as $part) {
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
}
