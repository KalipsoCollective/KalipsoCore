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
}
