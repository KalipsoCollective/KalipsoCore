<?php

/**
 * @package KX\Core
 * @subpackage Middleware
 */

declare(strict_types=1);

namespace KX\Core;


class Middleware
{

    public $next = false;
    public $passVariables = [];
    public $redirect = false;

    /**
     * Run the middleware
     * @param array $passVariables
     * @return object
     */
    public function next(array $passVariables = []): object
    {
        $this->next = true;
        if (!empty($passVariables)) {
            $this->passVariables = $passVariables;
        }
        return $this;
    }

    /**
     * Redirect
     * @param string $url
     * @param int $statusCode
     * @return object
     */
    public function redirect(string $url, int $statusCode = 302): object
    {
        $this->redirect = [
            'url' => $url,
            'statusCode' => $statusCode
        ];
        return $this;
    }

    /**
     * Get the parameters
     * @return array
     */
    public function getParameters(): array
    {
        return $this->passVariables;
    }

    /**
     * Is next called
     * @return bool
     */
    public function isNextCalled(): bool
    {
        return $this->next;
    }

    /**
     * Is redirect called
     * @return bool|array
     */
    public function isRedirectCalled(): bool|array
    {
        return $this->redirect;
    }
}
