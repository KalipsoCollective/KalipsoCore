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
  private $responseCode;
  private $responseMessage;
  private $responseHeader;
  private $responseBody;

  /**
   * Response constructor
   * @return object
   */
  public function __construct()
  {
    $this->response = [];
    $this->responseCode = 200;
    $this->responseMessage = 'OK';
    $this->responseHeader = [];
    $this->responseBody = '';

    return $this;
  }

  /**
   * Set response code
   * @param int $code
   * @return object
   */
  public function setResponseCode(int $code): object
  {
    $this->responseCode = $code;
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
}
