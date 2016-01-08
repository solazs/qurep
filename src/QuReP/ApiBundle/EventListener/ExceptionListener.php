<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.11.17.
 * Time: 0:27
 */

namespace QuReP\ApiBundle\EventListener;


use QuReP\ApiBundle\Exception\ExceptionConsts;
use QuReP\ApiBundle\Exception\IQuRePException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExceptionListener
{

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        if ($exception instanceof IQuRePException) {
            // Custom QuReP exceptions
            // inject custom error codes/error constraints to exception types here.


            $response = new Response(json_encode(array(
                'message' => $exception->getMessage(),
                'code' => $exception->getCode() == null ? null : $exception->getCode()
            )));

            $event->setResponse($response);
        } elseif ($exception instanceof NotFoundHttpException) {
            // 404
            $response = new Response(json_encode(array(
                'message' => $exception->getMessage(),
                'code' => ExceptionConsts::NOTFOUNDERROR,
                'error' => 404
            )));

            $event->setResponse($response);
        } else {
            // catch others
            $response = new Response(json_encode(array(
                'message' => $exception->getMessage(),
                'code' => $exception->getCode()
            )));

            $event->setResponse($response);
        }
    }
}