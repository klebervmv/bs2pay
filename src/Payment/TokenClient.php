<?php

namespace Adiq\Payment;

use Adiq\Auth\AuthManager;
use Adiq\Client\HttpClient;
use Adiq\Client\Request;
use Adiq\Config\Config;
use Adiq\Dto\CardDto;
use Adiq\Exceptions\ValidationException;
use Adiq\Utils\Helper;
use Adiq\Utils\Validator;

/**
 * Tokenização transacional de cartão.
 *
 * POST /v1/tokens/cards — gera um numberToken com validade ~10 minutos
 * para uso imediato em uma cobrança. Para armazenamento de longo prazo,
 * use VaultClient.
 */
class TokenClient
{
    /** @var HttpClient */
    private $http;

    public function __construct(Config $config, AuthManager $authManager)
    {
        $this->http = new HttpClient($config, $authManager);
    }

    /**
     * Gera token transacional para um cartão.
     *
     * @param array $data ['cardNumber' => '5155901222250004']
     * @return CardDto
     * @throws ValidationException
     */
    public function create(array $data)
    {
        Validator::assertRequired($data, ['cardNumber']);

        $cardNumber = Helper::onlyDigits($data['cardNumber']);
        if (!Validator::validateCardNumber($cardNumber)) {
            throw new ValidationException('Número de cartão inválido.', [
                'cardNumber' => 'PAN inválido (Luhn ou comprimento).',
            ]);
        }

        $response = $this->http->send(new Request(
            'POST',
            '/v1/tokens/cards',
            ['cardNumber' => $cardNumber]
        ));

        return new CardDto($response['httpCode'], $response['body'], $response['raw']);
    }
}
