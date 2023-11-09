<?php

/**
 * @package KX
 * @subpackage Core\Auth
 */

declare(strict_types=1);

namespace KX\Core;

class Auth
{

    /**
     * Factory inheritances 
     **/
    public $request;
    public $response;

    /**
     *  @param object container  factory class   
     *  @return void
     **/

    public function __construct($arguments)
    {

        $this->request = $arguments['request'];
        $this->response = $arguments['response'];
    }
}
