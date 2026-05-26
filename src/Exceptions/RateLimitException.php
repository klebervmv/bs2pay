<?php

namespace Adiq\Exceptions;

/**
 * Limite de requisições excedido (HTTP 429).
 */
class RateLimitException extends AdiqException
{
    /** @var int|null */
    protected $retryAfter;

    public function __construct($message = 'Limite de requisições excedido.', $retryAfter = null, $httpCode = 429, $responseBody = null)
    {
        parent::__construct($message, 0, $httpCode, $responseBody);
        $this->retryAfter = $retryAfter;
    }

    /** @return int|null Segundos sugeridos para aguardar antes de nova tentativa */
    public function getRetryAfter()
    {
        return $this->retryAfter;
    }
}
