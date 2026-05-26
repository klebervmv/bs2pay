<?php

namespace Adiq\Webhook;

use Adiq\Auth\AuthManager;
use Adiq\Client\HttpClient;
use Adiq\Client\Request;
use Adiq\Config\Config;
use Adiq\Dto\ResponseDto;
use Adiq\Exceptions\ValidationException;

/**
 * Gerenciamento de webhooks na ADIQ.
 *
 * Endpoint: POST /v1/merchant/webhook  (configuração da URL de callback).
 */
class WebhookManager
{
    /** @var HttpClient */
    private $http;

    public function __construct(Config $config, AuthManager $authManager)
    {
        $this->http = new HttpClient($config, $authManager);
    }

    /**
     * Registra/atualiza a URL de webhook do merchant.
     *
     * @param string $url     URL HTTPS que receberá os callbacks
     * @param array  $headers Headers customizados (assoc) que a ADIQ enviará
     * @return ResponseDto
     * @throws ValidationException
     */
    public function register($url, array $headers = [])
    {
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ValidationException('URL de webhook inválida.');
        }
        if (stripos($url, 'https://') !== 0) {
            throw new ValidationException('URL de webhook deve usar HTTPS.');
        }

        $body = ['url' => $url];
        if (!empty($headers)) {
            $body['headers'] = $headers;
        }

        $response = $this->http->send(new Request('POST', '/v1/merchant/webhook', $body));

        return new ResponseDto($response['httpCode'], $response['body'], $response['raw']);
    }

    /**
     * Consulta configuração atual de webhook.
     *
     * @return ResponseDto
     */
    public function getConfig()
    {
        $response = $this->http->send(new Request('GET', '/v1/merchant/webhook'));
        return new ResponseDto($response['httpCode'], $response['body'], $response['raw']);
    }

    /**
     * Validador estático para uso no handler que recebe o callback da ADIQ.
     *
     * @return string Nome da classe WebhookValidator (atalho conveniente)
     */
    public function validator()
    {
        return WebhookValidator::class;
    }
}
