<?php


namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    private $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelException(ExceptionEvent $event)
    {

        // You get the exception object from the received event
        $exception = $event->getThrowable();
        if(!$exception->getCode() && $exception instanceof HttpExceptionInterface){
            $code = $exception->getStatusCode();
        }elseif(!$exception->getCode()) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
        }else{
            $code = $exception->getCode();
        }

        $message = sprintf(
            "My Error says: \"%s\" with code: %s",
            $exception->getMessage(),
            $code
        );
        //log error
        $this->logger->error($message);
        // Customize your response object to display the exception details
        $response = new Response();
        $response->setContent($message);

        // HttpExceptionInterface is a special type of exception that
        // holds status code and header details
        if ($exception instanceof HttpExceptionInterface) {
            $response->setStatusCode($exception->getStatusCode());
            $response->headers->replace($exception->getHeaders());
        } else {
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // sends the modified response object to the event
        $event->setResponse($response);
    }
}