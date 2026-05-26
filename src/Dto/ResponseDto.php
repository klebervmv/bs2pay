<?php

namespace Adiq\Dto;

use Adiq\Utils\Helper;

/**
 * Resposta base. Encapsula o status HTTP e o corpo decodificado da resposta.
 *
 * Subclasses fornecem getters específicos para cada endpoint (PaymentResponse,
 * TokenResponse, VaultResponse, etc).
 */
class ResponseDto
{
    /** @var int */
    protected $httpCode;

    /** @var array */
    protected $body;

    /** @var string */
    protected $raw;

    /**
     * @param int        $httpCode
     * @param array|null $body
     * @param string     $raw
     */
    public function __construct($httpCode, $body, $raw = '')
    {
        $this->httpCode = (int) $httpCode;
        $this->body = is_array($body) ? $body : [];
        $this->raw = (string) $raw;
    }

    /** @return bool */
    public function isSuccess()
    {
        return $this->httpCode >= 200 && $this->httpCode < 300;
    }

    /** @return int */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /** @return array */
    public function getBody()
    {
        return $this->body;
    }

    /** @return string */
    public function getRaw()
    {
        return $this->raw;
    }

    /**
     * Acesso direto por path (dot-notation).
     *
     * @param string $path
     * @param mixed  $default
     * @return mixed
     */
    public function get($path, $default = null)
    {
        return Helper::get($this->body, $path, $default);
    }

    /** @return array */
    public function toArray()
    {
        return $this->body;
    }
}
