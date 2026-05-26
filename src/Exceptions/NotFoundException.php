<?php

namespace Adiq\Exceptions;

/**
 * Recurso não encontrado (HTTP 404).
 */
class NotFoundException extends AdiqException
{
    public function __construct($message = 'Recurso não encontrado.', $httpCode = 404, $responseBody = null)
    {
        parent::__construct($message, 0, $httpCode, $responseBody);
    }
}
