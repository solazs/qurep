<?php

namespace Solazs\QuReP\ApiBundle\EventListener;


use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Solazs\QuReP\ApiBundle\Exception\ExceptionConsts;
use Solazs\QuReP\ApiBundle\Exception\FormErrorException;
use Solazs\QuReP\ApiBundle\Exception\IQuRePException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * Class ExceptionListener
 *
 * Class to catch all exceptions and return json formatted error response
 *
 * @package Solazs\QuReP\ApiBundle\EventListener
 */
class ExceptionListener
{
    protected $serializer;
    protected $logger;
    protected $env;

    public function __construct(SerializerInterface $serializer, LoggerInterface $logger, string $env)
    {
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->env = $env;
    }

    /**
     * Listener for exception kernel event
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        // Default logging level
        $level = 'info';

        if ($exception instanceof IQuRePException) {
            // Custom QuReP exceptions
            // inject custom error codes/error constraints to exception types here.

            $response = new Response(
              $this->serializer->serialize(
                array(
                  'error' => $exception->getMessage(),
                  'code'  => $exception->getCode() == null ? null : $exception->getCode(),
                ),
                'json'
              )
            );
        } elseif ($exception instanceof FormErrorException) {
            $response = new Response(
              $this->serializer->serialize(
                array(
                  'error' => $exception->getMessage(),
                  'form'  => $exception->getForm(),
                  'code'  => ExceptionConsts::BADREQUEST,
                ),
                'json'
              ), ExceptionConsts::BADREQUEST
            );
            $response->setStatusCode(400);
        } elseif ($exception instanceof BadRequestHttpException) {
            // 400
            $response = new Response(
              $this->serializer->serialize(
                array(
                  'error' => $exception->getMessage(),
                  'code'  => ExceptionConsts::BADREQUEST,
                ),
                'json'
              ), ExceptionConsts::BADREQUEST
            );
        } elseif ($exception instanceof AuthenticationCredentialsNotFoundException) {
            // 401
            $response = new Response(
              $this->serializer->serialize(
                array(
                  'error' => "Login credentials not found",
                  'code'  => ExceptionConsts::UNAUTHORIZED,
                ),
                'json'
              ), ExceptionConsts::UNAUTHORIZED
            );
            $level = 'notice';
        } elseif ($exception instanceof AccessDeniedException) {
            // 403
            $response = new Response(
              $this->serializer->serialize(
                array(
                  'error' => "Forbidden",
                  'code'  => ExceptionConsts::FORBIDDEN,
                ),
                'json'
              ), ExceptionConsts::FORBIDDEN
            );
            $level = 'notice';
        } elseif ($exception instanceof NotFoundHttpException) {
            // 404
            $response = new Response(
              $this->serializer->serialize(
                array(
                  'error' => $exception->getMessage(),
                  'code'  => ExceptionConsts::NOTFOUNDERROR,
                ),
                'json'
              ), ExceptionConsts::NOTFOUNDERROR
            );
        } elseif ($exception instanceof MethodNotAllowedException) {
            // 405
            $response = new Response(
              $this->serializer->serialize(
                array(
                  'error' => $exception->getMessage(),
                  'code'  => ExceptionConsts::METHODNOTALLOWED,
                ),
                'json'
              ), ExceptionConsts::METHODNOTALLOWED
            );
        } else {
            // catch others (500)
            $payload = [
              'error'     => $exception->getMessage(),
              'code'      => $exception->getCode(),
              'exception' => get_class($exception),
            ];
            if ($this->env === 'dev') {
                $payload['trace'] = $exception->getTraceAsString();
            }
            $response = new Response(
              $this->serializer->serialize($payload, 'json'),
              500
            );
            $level = 'critical';
        }

        $this->logger->$level(
          get_class($exception).' caught, returning error message. Exception message: '.$exception->getMessage(),
          $level !== 'info' ?
            ['stackTrace' => $exception->getTraceAsString()] : []
        );

        $response->headers->set("content-type", "application/json");

        $event->setResponse($response);
    }
}