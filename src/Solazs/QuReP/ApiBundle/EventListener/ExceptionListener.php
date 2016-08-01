<?php
/**
 * Created by PhpStorm.
 * User: baloo
 * Date: 2015.11.17.
 * Time: 0:27
 */

namespace Solazs\QuReP\ApiBundle\EventListener;


use Psr\Log\LoggerInterface;
use Solazs\QuReP\ApiBundle\Exception\ExceptionConsts;
use Solazs\QuReP\ApiBundle\Exception\FormErrorException;
use Solazs\QuReP\ApiBundle\Exception\IQuRePException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

class ExceptionListener
{
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        $level = 'info';

        if ($exception instanceof IQuRePException) {
            // Custom QuReP exceptions
            // inject custom error codes/error constraints to exception types here.

            $response = new Response(
              json_encode(
                array(
                  'error' => $exception->getMessage(),
                  'code'  => $exception->getCode() == null ? null : $exception->getCode(),
                )
              )
            );
        } elseif ($exception instanceof FormErrorException) {
            // 400
            $response = new Response(
              json_encode(
                array(
                  'error' => $exception->getErrorArray(),
                  'code'  => ExceptionConsts::BADREQUEST,
                )
              ), ExceptionConsts::BADREQUEST
            );
            $response->setStatusCode(400);
        } elseif ($exception instanceof BadRequestHttpException) {
            // 400
            $response = new Response(
              json_encode(
                array(
                  'error' => $exception->getMessage(),
                  'code'  => ExceptionConsts::BADREQUEST,
                )
              ), ExceptionConsts::BADREQUEST
            );
        } elseif ($exception instanceof AuthenticationCredentialsNotFoundException) {
            // 403
            $response = new Response(
              json_encode(
                array(
                  'error' => "Login credentials not found",
                  'code'  => ExceptionConsts::UNAUTHORIZED,
                )
              ), ExceptionConsts::UNAUTHORIZED
            );
            $level = 'warning';
        } elseif ($exception instanceof NotFoundHttpException) {
            // 404
            $response = new Response(
              json_encode(
                array(
                  'error' => $exception->getMessage(),
                  'code'  => ExceptionConsts::NOTFOUNDERROR,
                )
              ), ExceptionConsts::NOTFOUNDERROR
            );
        } else {
            // catch others
            $response = new Response(
              json_encode(
                array(
                  'error'     => $exception->getMessage(),
                  'code'      => $exception->getCode(),
                  'exception' => get_class($exception),
                )
              )
            );
            $level = 'error';
        }

        $this->logger->$level(
          get_class($exception).' caught, returning error message. Exception message: ' . $exception->getMessage(),
          $level == 'error' ?
            ['stackTrace' => $exception->getTraceAsString()] : []
        );

        $response->headers->set("content-type", "application/json");

        $event->setResponse($response);
    }
}