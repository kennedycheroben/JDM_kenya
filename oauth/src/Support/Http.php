<?php

declare(strict_types=1);

namespace Jdm\OAuth\Support;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class Http
{
    public static function request(): \Psr\Http\Message\ServerRequestInterface
    {
        return ServerRequestFactory::fromGlobals();
    }

    public static function response(): ResponseInterface
    {
        return new Response();
    }

    public static function emit(ResponseInterface $response): never
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name.': '.$value, false);
            }
        }
        echo $response->getBody();
        exit;
    }

    public static function oauthError(OAuthServerException $exception): never
    {
        self::emit($exception->generateHttpResponse(new Response()));
    }

    public static function safeError(int $status = 400): never
    {
        self::emit(new JsonResponse(['error' => 'The sign-in request could not be completed.'], $status));
    }

    public static function logUnexpected(Throwable $exception): void
    {
        error_log('OAuth endpoint failure: '.get_class($exception));
    }
}
