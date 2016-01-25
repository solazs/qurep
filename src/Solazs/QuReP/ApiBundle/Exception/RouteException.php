<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.11.16.
 * Time: 23:55
 */

namespace Solazs\QuReP\ApiBundle\Exception;


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