<?php
/**
 * @package KX
 * @subpackage Core\Middleware
 */

declare(strict_types=1);

namespace KX\Core;

class Middleware {

    /**
     * Factory inheritances 
     **/
    protected $container;


    /**
     *  @param object container  factory class   
     *  @return void
     **/

    public function __construct($container) {

        $this->container = $container;

    }

    public function get($key = null) {

        return is_null($key) ? $this->container : $this->container->{$key};

    }

}