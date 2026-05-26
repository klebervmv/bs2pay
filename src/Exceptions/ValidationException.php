<?php

namespace Adiq\Exceptions;

/**
 * Erro de validação de entrada (HTTP 400 ou validação local).
 */
class ValidationException extends AdiqException
{
    /** @var array<string,string> */
    protected $errors = [];

    /**
     * @param string $message
     * @param array  $errors  Mapa de campo => mensagem
     * @param int    $httpCode
     * @param mixed  $responseBody
     */
    public function __construct($message = 'Erro de validação.', array $errors = [], $httpCode = 400, $responseBody = null)
    {
        parent::__construct($message, 0, $httpCode, $responseBody);
        $this->errors = $errors;
    }

    /** @return array<string,string> */
    public function getErrors()
    {
        return $this->errors;
    }
}
