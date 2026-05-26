<?php

namespace Adiq\Payment;

use Adiq\Auth\AuthManager;
use Adiq\Client\HttpClient;
use Adiq\Client\Request;
use Adiq\Config\Config;
use Adiq\Dto\BinResponseDto;
use Adiq\Exceptions\ValidationException;
use Adiq\Utils\Helper;

/**
 * Consulta de BIN (Bank Identification Number).
 *
 * GET /v1/bins/search/{binNumber}
 */
class BinClient
{
    /** @var HttpClient */
    private $http;

    public function __construct(Config $config, AuthManager $authManager)
    {
        $this->http = new HttpClient($config, $authManager);
    }

    /**
     * Consulta informações de banco, bandeira, país e tipo do cartão.
     *
     * GET /v1/bins/search/{binNumber} retorna um array de entradas.
     *
     * @param string $binNumber 6 a 8 primeiros dígitos do PAN
     * @return BinResponseDto
     * @throws ValidationException
     */
    public function search($binNumber)
    {
        $bin = Helper::onlyDigits($binNumber);
        if (strlen($bin) < 6 || strlen($bin) > 8) {
            throw new ValidationException('BIN deve ter entre 6 e 8 dígitos.', [
                'binNumber' => 'Comprimento inválido.',
            ]);
        }

        $response = $this->http->send(new Request(
            'GET',
            '/v1/bins/search/' . $bin
        ));

        return new BinResponseDto($response['httpCode'], $response['body'], $response['raw']);
    }
}
