<?php

namespace Adiq\Exceptions;

/**
 * Erros de autenticação (HTTP 401, falha na geração/renovação de token).
 */
class AuthException extends AdiqException
{
    public function __construct($message = 'Falha de autenticação.', $httpCode = 401, $responseBody = null)
    {
        parent::__construct($message, 0, $httpCode, $responseBody);
    }
}
