<?php

namespace Adiq\Exceptions;

/**
 * Erros de rede: timeout, conexão recusada, DNS, 5xx persistente.
 */
class NetworkException extends AdiqException
{
    public function __construct($message = 'Erro de rede ao comunicar com a ADIQ.', $httpCode = null, $responseBody = null)
    {
        parent::__construct($message, 0, $httpCode, $responseBody);
    }
}
