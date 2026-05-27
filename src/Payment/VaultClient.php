<?php

namespace Adiq\Payment;

use Adiq\Auth\AuthManager;
use Adiq\Client\HttpClient;
use Adiq\Client\Request;
use Adiq\Config\Config;
use Adiq\Dto\CardDto;
use Adiq\Exceptions\ValidationException;
use Adiq\Utils\Validator;

/**
 * Armazenamento de cartões em Vault (POST /v1/vaults/cards).
 *
 * Recebe um numberToken (obtido via TokenClient) + dados de expiração
 * e retorna um vaultId para uso em transações recorrentes / one-click.
 *
 * Opcionalmente realiza Zero Auth para validar o cartão antes de armazenar.
 */
class VaultClient
{
    /** @var HttpClient */
    private $http;

    public function __construct(Config $config, AuthManager $authManager)
    {
        $this->http = new HttpClient($config, $authManager);
    }

    /**
     * Armazena um cartão no Vault.
     *
     * @param array $data {
     *   numberToken: string,
     *   cardholderName: string,
     *   securityCode?: string,
     *   brand: string,
     *   expirationMonth: string,
     *   expirationYear: string,
     *   verifyCard?: bool,
     *   platformType?: 'credit'|'debit'   // Default: 'credit'.
     *                                     // Necessário em algumas integrações para
     *                                     // diferenciar vault de crédito x débito.
     * }
     * @return CardDto
     * @throws ValidationException
     */
    public function store(array $data)
    {
        Validator::assertRequired($data, [
            'numberToken',
            'cardholderName',
            'brand',
            'expirationMonth',
            'expirationYear',
        ]);

        if (!Validator::validateExpirationDate($data['expirationMonth'], $data['expirationYear'])) {
            throw new ValidationException('Data de expiração inválida.', [
                'expirationMonth' => 'Mês/Ano inválido ou cartão vencido.',
            ]);
        }

        $platformType = isset($data['platformType']) && $data['platformType'] !== ''
            ? strtolower((string) $data['platformType'])
            : 'credit';
        if (!in_array($platformType, ['credit', 'debit'], true)) {
            throw new ValidationException("platformType deve ser 'credit' ou 'debit'.");
        }

        $body = [
            'numberToken' => $data['numberToken'],
            'cardholderName' => $data['cardholderName'],
            'brand' => strtolower($data['brand']),
            'expirationMonth' => str_pad((string) $data['expirationMonth'], 2, '0', STR_PAD_LEFT),
            'expirationYear' => (string) $data['expirationYear'],
            'platformType' => $platformType,
        ];

        if (!empty($data['securityCode'])) {
            $body['securityCode'] = (string) $data['securityCode'];
        }
        if (isset($data['verifyCard'])) {
            $body['verifyCard'] = (bool) $data['verifyCard'];
        }

        $response = $this->http->send(new Request('POST', '/v1/vaults/cards', $body));

        return new CardDto($response['httpCode'], $response['body'], $response['raw']);
    }

    /**
     * Consulta um vaultId.
     *
     * @param string $vaultId
     * @return CardDto
     */
    public function get($vaultId)
    {
        if (empty($vaultId)) {
            throw new ValidationException('vaultId é obrigatório.');
        }

        $response = $this->http->send(new Request(
            'GET',
            '/v1/vaults/cards/' . rawurlencode($vaultId)
        ));

        return new CardDto($response['httpCode'], $response['body'], $response['raw']);
    }

    /**
     * Remove um cartão do Vault.
     *
     * @param string $vaultId
     * @return bool true se removido com sucesso
     */
    public function delete($vaultId)
    {
        if (empty($vaultId)) {
            throw new ValidationException('vaultId é obrigatório.');
        }

        $response = $this->http->send(new Request(
            'DELETE',
            '/v1/vaults/cards/' . rawurlencode($vaultId)
        ));

        return $response['httpCode'] >= 200 && $response['httpCode'] < 300;
    }
}
