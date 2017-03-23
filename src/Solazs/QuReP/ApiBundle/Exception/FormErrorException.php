<?php

namespace Solazs\QuReP\ApiBundle\Exception;

use Symfony\Component\Form\FormInterface;


/**
 * Class FormErrorException
 *
 * Custom exception for Form errors to guarantee proper serialization of form errors.
 *
 * @package Solazs\QuReP\ApiBundle\Exception
 */
class FormErrorException extends \Exception
{
    protected $form;

    public function __construct(FormInterface $form, $code = 400, \Exception $previous = null)
    {
        $this->form = $form;
        parent::__construct("Invalid Form", $code, $previous);
    }

    /**
     * @return FormInterface
     */
    public function getForm()
    {
        return $this->form;
    }
}