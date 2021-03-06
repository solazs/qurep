<?php

namespace Solazs\QuReP\ApiBundle\Exception;

/**
 * Class RouteException
 *
 * Exceptions mainly for handling errors during request parse.
 *
 * @package Solazs\QuReP\ApiBundle\Exception
 */
class RouteException extends \Exception implements IQuRePException
{
    public function __construct(
      $message = 'An error happened in routing',
      $code = ExceptionConsts::ROUTINGERROR,
      \Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}