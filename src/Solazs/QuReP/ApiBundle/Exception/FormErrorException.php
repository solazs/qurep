<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2016.02.06.
 * Time: 18:55
 */

namespace Solazs\QuReP\ApiBundle\Exception;


class FormErrorException extends \Exception
{
    private $errorArray;

    public function __construct(array $errorArray, $code = 400, \Exception $previous = null)
    {
        $this->errorArray = $errorArray;
        parent::__construct(var_export($this->errorArray, true), $code, $previous);
    }

    /**
     * @return array
     */
    public function getErrorArray()
    {
        return $this->errorArray;
    }
}