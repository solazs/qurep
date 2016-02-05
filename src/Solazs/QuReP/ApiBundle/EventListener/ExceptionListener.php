<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.11.17.
 * Time: 0:27
 */

namespace Solazs\QuReP\ApiBundle\EventListener;


use Solazs\QuReP\ApiBundle\Exception\ExceptionConsts;
use Solazs\QuReP\ApiBundle\Exception\IQuRePException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
                'error' => $exception->getMessage(),
                'code' => $exception->getCode() == null ? null : $exception->getCode()
            )));

            $event->setResponse($response);
        } elseif ($exception instanceof BadRequestHttpException) {
            // 400
            $response = new Response(json_encode(array(
                'error' => $exception->getMessage(),
                'code' => ExceptionConsts::BADREQUEST,
            )));

            $event->setResponse($response);
        } elseif ($exception instanceof NotFoundHttpException) {
            // 404
            $response = new Response(json_encode(array(
                'error' => $exception->getMessage(),
                'code' => ExceptionConsts::NOTFOUNDERROR,
            )));

            $event->setResponse($response);
        } else {
            // catch others
            $response = new Response(json_encode(array(
                'error' => $exception->getMessage(),
                'code' => $exception->getCode()
            )));

            $event->setResponse($response);
        }
    }
}