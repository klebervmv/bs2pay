<?php

namespace Adiq\Client;

/**
 * Encapsulação de uma requisição HTTP a ser enviada pelo HttpClient.
 */
class Request
{
    /** @var string */
    private $method;

    /** @var string */
    private $endpoint;

    /** @var array|null */
    private $body;

    /** @var array<string,string> */
    private $headers;

    /** @var array<string,string|int> */
    private $query;

    /** @var bool */
    private $requireAuth;

    /**
     * @param string $method      GET, POST, PUT, DELETE
     * @param string $endpoint    Caminho a partir da baseUrl (ex.: /v1/payments)
     * @param array  $body        Corpo (será JSON-encodado)
     * @param array  $headers     Headers adicionais
     * @param array  $query       Querystring
     * @param bool   $requireAuth Se true, exige token Bearer (default true)
     */
    public function __construct($method, $endpoint, array $body = null, array $headers = [], array $query = [], $requireAuth = true)
    {
        $this->method = strtoupper($method);
        $this->endpoint = $endpoint;
        $this->body = $body;
        $this->headers = $headers;
        $this->query = $query;
        $this->requireAuth = (bool) $requireAuth;
    }

    /** @return string */
    public function getMethod()
    {
        return $this->method;
    }

    /** @return string */
    public function getEndpoint()
    {
        $endpoint = $this->endpoint;
        if (!empty($this->query)) {
            $qs = http_build_query($this->query);
            $endpoint .= (strpos($endpoint, '?') === false ? '?' : '&') . $qs;
        }
        return $endpoint;
    }

    /** @return array|null */
    public function getBody()
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function getHeaders()
    {
        return $this->headers;
    }

    /** @return bool */
    public function requireAuth()
    {
        return $this->requireAuth;
    }
}
