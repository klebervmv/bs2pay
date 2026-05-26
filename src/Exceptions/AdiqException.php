<?php

namespace Adiq\Exceptions;

use Exception;

/**
 * Base exception do SDK ADIQ. Todas as outras exceptions herdam desta.
 */
class AdiqException extends Exception
{
    /** @var int|null */
    protected $httpCode;

    /** @var mixed */
    protected $responseBody;

    /** @var string|null */
    protected $errorTag;

    /**
     * @param string         $message
     * @param int            $code
     * @param int|null       $httpCode
     * @param mixed          $responseBody
     * @param string|null    $errorTag
     * @param Exception|null $previous
     */
    public function __construct(
        $message = '',
        $code = 0,
        $httpCode = null,
        $responseBody = null,
        $errorTag = null,
        Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
        $this->responseBody = $responseBody;
        $this->errorTag = $errorTag;
    }

    /** @return int|null */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /** @return mixed */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /** @return string|null */
    public function getErrorTag()
    {
        return $this->errorTag;
    }
}
