<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.11.17.
 * Time: 0:27
 */

namespace QuReP\ApiBundle\EventListener;


use QuReP\ApiBundle\Exception\IQuRePException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

class ExceptionListener
{

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        if ($exception instanceof IQuRePException) {
            $response = new Response($exception->getMessage());

            $event->setResponse($response);
        }
    }
}