<?php

namespace Adiq\Payment;

use Adiq\Auth\AuthManager;
use Adiq\Client\HttpClient;
use Adiq\Client\Request;
use Adiq\Config\Config;
use Adiq\Dto\ZeroAuthResponseDto;
use Adiq\Exceptions\ValidationException;

/**
 * Validação de cartão sem cobrança (POST /v1/zero-auth).
 *
 * Aceita numberToken (transacional) ou vaultId.
 */
class ZeroAuthClient
{
    /** @var HttpClient */
    private $http;

    public function __construct(Config $config, AuthManager $authManager)
    {
        $this->http = new HttpClient($config, $authManager);
    }

    /**
     * Executa Zero Auth (validação de cartão sem cobrança).
     *
     * Resposta: {
     *   "brand": "Visa",
     *   "cardNumber": "1234********4644",
     *   "transactionType": "credit",
     *   "cardAuthSuccess": true,
     *   "message": "optional error message"
     * }
     *
     * @param array $data {
     *   numberToken?: string (token transacional),
     *   vaultId?: string (ou numberToken),
     *   cardholderName?: string,
     *   securityCode?: string,
     *   expirationMonth?: string,
     *   expirationYear?: string,
     *   transactionType: "credit"|"debit"
     * }
     * @return ZeroAuthResponseDto
     * @throws ValidationException
     */
    public function verify(array $data)
    {
        if (empty($data['numberToken']) && empty($data['vaultId'])) {
            throw new ValidationException('Informe numberToken ou vaultId.');
        }

        $response = $this->http->send(new Request('POST', '/v1/zero-auth', $data));

        return new ZeroAuthResponseDto($response['httpCode'], $response['body'], $response['raw']);
    }
}
