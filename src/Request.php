<?php

/**
 * @package KX\Core
 * @subpackage Request
 */

declare(strict_types=1);

namespace KX\Core;

final class Request
{

    private $request;
    private $requestUri;
    private $requestMethod;
    private $queryString;
    private $getParams;
    private $postParams;
    private $header;
    private $middlewareParams;



    /**
     * Request constructor
     * @return object
     */
    public function __construct()
    {

        $this->request = $_REQUEST;
        $this->requestUri = $_SERVER['REQUEST_URI'];
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->queryString = $_SERVER['QUERY_STRING'];
        $this->header = getallheaders();
        $this->getParams = $_GET;
        $this->postParams = $_POST;
        $this->middlewareParams = [];


        return $this;
    }

    /**
     * Get request method
     * @return string
     */
    public function getRequestMethod(): string
    {
        return $this->requestMethod;
    }

    /**
     * Get request uri
     * @return string
     */
    public function getUri(): string
    {
        return $this->requestUri;
    }

    /**
     * Get query string
     * @return string
     */
    public function getQueryString(): string
    {
        return $this->queryString;
    }

    /**
     * Get request header
     * @return array
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * Get request params
     * @return array
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * Get request params
     * @return array
     */
    public function getGetParams(): array
    {
        return $this->getParams;
    }

    /**
     * Get request params
     * @return array
     */
    public function getPostParams(): array
    {
        return $this->postParams;
    }

    /**
     * Get request params
     * @return array
     */
    public function getParams(): array
    {
        return array_merge($this->getParams, $this->postParams);
    }

    /**
     * Get request params
     * @return any
     */
    public function getParam(string $key)
    {
        return isset($this->getParams()[$key]) !== false ? $this->getParams()[$key] : false;
    }

    /**
     * Get middleware params
     * @return array
     */
    public function getMiddlewareParams(): array
    {
        return $this->middlewareParams;
    }

    /**
     * Set middleware params
     * @param array $params
     * @return object
     */
    public function setMiddlewareParams(array $params): object
    {
        $this->middlewareParams = $params;
        return $this;
    }
}
