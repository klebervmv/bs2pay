<?php

namespace Adiq\Client;

use Adiq\Auth\AuthManager;
use Adiq\Config\Config;
use Adiq\Exceptions\AuthException;
use Adiq\Exceptions\NetworkException;
use Adiq\Exceptions\NotFoundException;
use Adiq\Exceptions\PaymentException;
use Adiq\Exceptions\RateLimitException;
use Adiq\Exceptions\ValidationException;
use Adiq\Utils\Helper;
use klebervmv\EasyCurl;

/**
 * Cliente HTTP construído sobre klebervmv\EasyCurl.
 *
 * Responsabilidades:
 *  - Montar requisições autenticadas (Bearer via AuthManager) ou não.
 *  - Mapear status HTTP em exceptions específicas.
 *  - Logar requisições/respostas com mascaramento de dados sensíveis.
 *
 * NÃO faz retry. Em um SDK de pagamento, retry automático em POST cria risco
 * de cobrança duplicada quando a resposta de um POST se perde após processamento
 * no servidor. Em caso de falha incerta (timeout/5xx em POST /v1/payments), o
 * caller deve consultar payments->getByOrderNumber() antes de decidir se reenvia.
 *
 * Cria uma instância de EasyCurl por requisição: a lib não reseta error
 * entre chamadas, o que tornaria reuso sujeito a estado residual. O custo
 * de curl_init() é negligível.
 */
class HttpClient
{
    /** @var Config */
    private $config;

    /** @var AuthManager|null */
    private $authManager;

    public function __construct(Config $config, AuthManager $authManager = null)
    {
        $this->config = $config;
        $this->authManager = $authManager;
    }

    /**
     * Envia a requisição.
     *
     * @param Request $request
     * @return array{httpCode:int,body:array|null,raw:string}
     *
     * @throws ValidationException|AuthException|NotFoundException|RateLimitException|PaymentException|NetworkException
     */
    public function send(Request $request)
    {
        $logger = $this->config->getLogger();

        $logger->debug('http.request', [
            'method' => $request->getMethod(),
            'endpoint' => $request->getEndpoint(),
            'body' => $request->getBody(),
        ]);

        $easyCurl = new EasyCurl(
            $this->config->getBaseUrl(),
            $this->config->getVerifySsl(),
            EasyCurl::CONTENTTYPJSON
        );

        $this->applyTimeout($easyCurl);

        // EasyCurl::render() já json_encoda o body internamente para contentType=json.
        $easyCurl->render(
            $request->getMethod(),
            $request->getEndpoint(),
            $request->getBody()
        );

        // Content-Type já é setado pelo construtor. Adicionamos apenas o resto.
        $easyCurl
            ->setHeader('Accept: application/json')
            ->setHeader('User-Agent: ' . $this->config->getUserAgent());

        if ($request->requireAuth()) {
            if ($this->authManager === null) {
                throw new AuthException('AuthManager não configurado para requisição autenticada.');
            }
            $easyCurl->setHeader('Authorization: Bearer ' . $this->authManager->getToken());
        }

        foreach ($request->getHeaders() as $name => $value) {
            $easyCurl->setHeader(is_int($name) ? $value : ($name . ': ' . $value));
        }

        try {
            $easyCurl->send();
        } catch (\Throwable $e) {
            throw new NetworkException('Falha ao executar requisição HTTP: ' . $e->getMessage());
        }

        if ($error = $easyCurl->getError()) {
            throw new NetworkException('Erro de rede: ' . $error);
        }

        $httpCode = (int) $easyCurl->getHttpCode();
        $result = $easyCurl->getResult(); // array (decodificado pelo EasyCurl) ou null se JSON inválido

        $body = is_array($result) ? $result : null;
        $raw = is_string($result) ? $result : Helper::jsonEncode($result);

        $logger->debug('http.response', [
            'httpCode' => $httpCode,
            'body' => $body,
        ]);

        $this->throwForStatus($httpCode, $body, $raw);

        return [
            'httpCode' => $httpCode,
            'body' => $body,
            'raw' => $raw,
        ];
    }

    /**
     * Aplica timeouts no handle cURL bruto.
     */
    private function applyTimeout(EasyCurl $easyCurl)
    {
        $handle = $easyCurl->getCurlInit();
        if (!$handle) {
            return;
        }
        $timeoutMs = $this->config->getTimeout();
        @curl_setopt($handle, CURLOPT_TIMEOUT_MS, $timeoutMs);
        @curl_setopt($handle, CURLOPT_CONNECTTIMEOUT_MS, min(10000, $timeoutMs));
    }

    /**
     * Mapeia status HTTP para exception específica. Não lança em 2xx.
     *
     * ADIQ retorna:
     *   - 2xx com { paymentAuthorization: { ... } } → sucesso
     *   - 4xx/5xx com [ { tag, description, ... } ] → erro (array)
     */
    private function throwForStatus($httpCode, $body, $raw)
    {
        if ($httpCode >= 200 && $httpCode < 300) {
            return;
        }

        $message = $this->extractMessage($body, $raw, $httpCode);

        // Extrai informações do erro — pode ser array ou object
        if (is_array($body) && !empty($body) && isset($body[0])) {
            // Resposta é um array de erros (ADIQ pattern)
            $firstError = $body[0];
            $tag = $firstError['tag'] ?? null;
            $returnCode = (is_numeric($tag)) ? $tag : null;
            $brand = $firstError['brand'] ?? null;
            $mac = $firstError['merchantAdviceCode'] ?? null;
        } else {
            $tag = Helper::get((array) $body, 'error.tag') ?: Helper::get((array) $body, 'tag');
            $returnCode = Helper::get((array) $body, 'returnCode');
            $brand = Helper::get((array) $body, 'brand');
            $mac = Helper::get((array) $body, 'merchantAdviceCode');
        }

        switch (true) {
            case $httpCode === 400:
                throw new ValidationException($message, $this->extractErrors($body), $httpCode, $body);
            case $httpCode === 401:
            case $httpCode === 403:
                throw new AuthException($message, $httpCode, $body);
            case $httpCode === 404:
                throw new NotFoundException($message, $httpCode, $body);
            case $httpCode === 429:
                $retryAfter = Helper::get((array) $body, 'retryAfter');
                throw new RateLimitException($message, $retryAfter, $httpCode, $body);
            case $httpCode >= 500:
                throw new NetworkException('Erro do servidor ADIQ: ' . $message, $httpCode, $body);
            default:
                throw new PaymentException(
                    $message,
                    $returnCode,
                    $brand,
                    $mac,
                    $httpCode,
                    $body,
                    $tag
                );
        }
    }

    private function extractMessage($body, $raw, $httpCode)
    {
        if (is_array($body) && !empty($body) && isset($body[0])) {
            // Array de erros ADIQ
            return $body[0]['description'] ?? (is_string($raw) && $raw !== '' ? $raw : sprintf('Requisição falhou com status %d.', $httpCode));
        }
        if (is_array($body)) {
            foreach (['errorDescription', 'message', 'error_description', 'detail', 'description'] as $key) {
                $v = Helper::get($body, $key);
                if (is_string($v) && $v !== '') {
                    return $v;
                }
            }
            $nested = Helper::get($body, 'error.description') ?: Helper::get($body, 'error.message');
            if (is_string($nested) && $nested !== '') {
                return $nested;
            }
        }
        if (is_string($raw) && $raw !== '' && $raw !== 'null') {
            return $raw;
        }
        return sprintf('Requisição falhou com status %d.', $httpCode);
    }

    private function extractErrors($body)
    {
        if (!is_array($body)) {
            return [];
        }
        if (isset($body['errors']) && is_array($body['errors'])) {
            return $body['errors'];
        }
        if (isset($body['violations']) && is_array($body['violations'])) {
            $out = [];
            foreach ($body['violations'] as $v) {
                $field = isset($v['propertyPath']) ? $v['propertyPath'] : (isset($v['field']) ? $v['field'] : 'unknown');
                $out[$field] = isset($v['message']) ? $v['message'] : 'Inválido';
            }
            return $out;
        }
        return [];
    }

    /** @return Config */
    public function getConfig()
    {
        return $this->config;
    }
}
