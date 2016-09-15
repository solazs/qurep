<?php

namespace Solazs\QuReP\ApiBundle\Exception;


/**
 * Class ExceptionConsts
 *
 * Contains constants for HTTP status codes
 *
 * @package Solazs\QuReP\ApiBundle\Exception
 */
abstract class ExceptionConsts
{
    const BADREQUEST = 400;
    const UNAUTHORIZED = 401;
    const FORBIDDEN = 403;
    const NOTFOUNDERROR = 404;
    const ROUTINGERROR = 1000;
}