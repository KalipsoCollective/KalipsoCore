<?php

/**
 * @package KX
 * @subpackage Core\Controller
 */

declare(strict_types=1);

namespace KX\Core;

use KX\Core\Helper;

class Controller
{

    public $container = null;
    public $module;
    public $modules;
    public $forms;
    public $form;

    /**
     *  @param object container  factory class   
     *  @return void
     **/

    public function __construct($container = null)
    {

        if ($container) {
            $this->container = $container;
        }
        $this->module = isset($this->get('request')->attributes['module']) !== false ?
            $this->get('request')->attributes['module'] : (
                isset($container->module) === false ?
                'general' :
                $container->module
            );
        $this->modules = file_exists($file = Helper::path('app/Resources/modules.php')) ?
            require $file :
            [];
        $this->form = isset($this->get('request')->attributes['form']) !== false ?
            $this->get('request')->attributes['form'] : (
                isset($container->form) === false ?
                'general' :
                $container->form
            );

        $this->forms = file_exists($file = Helper::path('app/Resources/forms.php')) ?
            require $file :
            [];
    }

    public function get($key = null)
    {

        return is_null($key) ?
            $this->container : (isset($this->container->{$key}) ?
                $this->container->{$key} :
                null
            );
    }
}
