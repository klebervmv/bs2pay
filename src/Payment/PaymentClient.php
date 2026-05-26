<?php

namespace Adiq\Payment;

use Adiq\Auth\AuthManager;
use Adiq\Client\HttpClient;
use Adiq\Client\Request;
use Adiq\Config\Config;
use Adiq\Dto\CancelDto;
use Adiq\Dto\CaptureDto;
use Adiq\Dto\PaymentDto;
use Adiq\Exceptions\ValidationException;
use Adiq\Utils\Helper;
use Adiq\Utils\Validator;

/**
 * Operações de pagamento (autorização, captura, cancelamento e consulta).
 *
 * Endpoints:
 *   POST   /v1/payments                                  Autorizar/capturar
 *   PUT    /v1/payments/{paymentId}/capture              Late capture
 *   PUT    /v1/payments/{paymentId}/cancel               Cancelamento/refund
 *   GET    /v1/payments/{paymentId}                      Consulta (v1)
 *   GET    /v2/payments/{paymentId}                      Consulta (v2, com dados extras)
 *   GET    /v1/payments/{orderNumber}/{transactionDate}  Consulta por orderNumber+data
 */
class PaymentClient
{
    /** @var HttpClient */
    private $http;

    public function __construct(Config $config, AuthManager $authManager)
    {
        $this->http = new HttpClient($config, $authManager);
    }

    /**
     * Cria (autoriza) um pagamento.
     *
     * Estrutura esperada (segue o contrato da ADIQ):
     * [
     *   'payment' => [
     *     'transactionType' => 'credit'|'debit',
     *     'amount' => 1000,                  // centavos
     *     'currencyCode' => 'brl',
     *     'productType' => 'avista'|'parcelado_loja'|'parcelado_emissor',
     *     'installments' => 1,
     *     'captureType' => 'ac'|'pa',        // ac = auth+capture, pa = pre-auth
     *     'recurrent' => false,
     *     'nridElo' => '...',                // recorrência Elo (opcional)
     *   ],
     *   'cardInfo' => [
     *     'numberToken' => '...',  // OU vaultId
     *     'vaultId' => '...',      // OU numberToken
     *     'cardholderName' => 'JOSE SILVA',
     *     'securityCode' => '123',
     *     'brand' => 'visa',
     *     'expirationMonth' => '01',
     *     'expirationYear' => '25',
     *   ],
     *   'customer' => [...],
     *   'sellerInfo' => [
     *     'orderNumber' => '0000000001',
     *     'softDescriptor' => 'LOJA*TEST',
     *   ],
     *   'sellers' => [...],          // marketplace (opcional)
     *   'lineItems' => [...],        // produtos (opcional)
     *   'deviceInfo' => [...],       // 3DS browser info (opcional)
     *   'threeDs' => [...],          // 3DS data (opcional)
     * ]
     *
     * @param array $data
     * @return PaymentDto
     * @throws ValidationException
     */
    public function create(array $data)
    {
        $this->validateCreate($data);
        $body = $this->buildCreateBody($data);

        $response = $this->http->send(new Request('POST', '/v1/payments', $body));

        return new PaymentDto($response['httpCode'], $response['body'], $response['raw']);
    }

    /**
     * Captura tardia de um pagamento previamente autorizado (PA).
     *
     * Resposta: { "captureAuthorization": { returnCode, description, paymentId, ... } }
     *
     * @param string   $paymentId
     * @param int|null $amount  Centavos. Se null, captura o valor total autorizado.
     * @param array    $sellers Captura parcial por seller (marketplace).
     * @return CaptureDto
     */
    public function capture($paymentId, $amount = null, array $sellers = [])
    {
        $this->assertId($paymentId, 'paymentId');

        $body = [];
        if ($amount !== null) {
            if (!Validator::validateAmount($amount)) {
                throw new ValidationException('amount inválido (deve ser inteiro positivo em centavos).');
            }
            $body['amount'] = (int) $amount;
        }
        if (!empty($sellers)) {
            $body['sellers'] = $sellers;
        }

        $response = $this->http->send(new Request(
            'PUT',
            '/v1/payments/' . rawurlencode($paymentId) . '/capture',
            $body
        ));

        return new CaptureDto($response['httpCode'], $response['body'], $response['raw']);
    }

    /**
     * Cancela / faz refund de um pagamento.
     *
     * Resposta: { "cancelAuthorization": { returnCode, description, paymentId, ... } }
     *
     * @param string   $paymentId
     * @param int|null $amount  Centavos. Se null, cancela o valor total.
     * @param array    $sellers Cancelamento parcial por seller (marketplace).
     * @return CancelDto
     */
    public function cancel($paymentId, $amount = null, array $sellers = [])
    {
        $this->assertId($paymentId, 'paymentId');

        $body = [];
        if ($amount !== null) {
            if (!Validator::validateAmount($amount)) {
                throw new ValidationException('amount inválido (deve ser inteiro positivo em centavos).');
            }
            $body['amount'] = (int) $amount;
        }
        if (!empty($sellers)) {
            $body['sellers'] = $sellers;
        }

        $response = $this->http->send(new Request(
            'PUT',
            '/v1/payments/' . rawurlencode($paymentId) . '/cancel',
            $body
        ));

        return new CancelDto($response['httpCode'], $response['body'], $response['raw']);
    }

    /**
     * Consulta um pagamento pelo paymentId.
     *
     * @param string $paymentId
     * @param string $version v1|v2
     * @return PaymentDto
     */
    public function get($paymentId, $version = 'v1')
    {
        $this->assertId($paymentId, 'paymentId');
        if (!in_array($version, ['v1', 'v2'], true)) {
            throw new ValidationException('version deve ser v1 ou v2.');
        }

        $response = $this->http->send(new Request(
            'GET',
            '/' . $version . '/payments/' . rawurlencode($paymentId)
        ));

        return new PaymentDto($response['httpCode'], $response['body'], $response['raw']);
    }

    /**
     * Valida um pagamento (pós-challenge 3DS).
     *
     * @param array $data {
     *   code3ds: string,
     *   validateToken: string
     * }
     * @return PaymentDto
     * @throws ValidationException
     */
    public function validate(array $data)
    {
        Validator::assertRequired($data, ['code3DS', 'validateToken']);

        $response = $this->http->send(new Request(
            'POST',
            '/v1/payments/validate',
            $data
        ));

        return new PaymentDto($response['httpCode'], $response['body'], $response['raw']);
    }

    /**
     * Consulta por orderNumber + data da transação.
     *
     * @param string         $orderNumber
     * @param string|int|null $transactionDate yyyyMMdd. Se null, usa data de hoje.
     * @return PaymentDto
     */
    public function getByOrderNumber($orderNumber, $transactionDate = null)
    {
        if (empty($orderNumber)) {
            throw new ValidationException('orderNumber é obrigatório.');
        }
        $date = $transactionDate === null ? Helper::dateYmd() : (string) $transactionDate;
        if (!preg_match('/^\d{8}$/', $date)) {
            throw new ValidationException('transactionDate deve estar no formato yyyyMMdd.');
        }

        $response = $this->http->send(new Request(
            'GET',
            '/v1/payments/' . rawurlencode($orderNumber) . '/' . $date
        ));

        return new PaymentDto($response['httpCode'], $response['body'], $response['raw']);
    }

    // ---------------------------------------------------------------
    // Construção / validação interna
    // ---------------------------------------------------------------

    /**
     * @param array $data
     * @throws ValidationException
     */
    private function validateCreate(array $data)
    {
        // Aceita tanto lowercase quanto PascalCase para as chaves raiz
        $payment = $data['payment'] ?? $data['Payment'] ?? [];
        $cardInfo = $data['cardInfo'] ?? $data['CardInfo'] ?? [];

        if (empty($payment)) {
            throw new ValidationException('O objeto Payment/payment é obrigatório.');
        }

        Validator::assertRequired($payment, [
            'transactionType',
            'amount',
            'currencyCode',
            'installments',
            'captureType',
        ]);

        $amount = $payment['amount'] ?? $payment['Amount'] ?? null;
        if (!Validator::validateAmount($amount)) {
            throw new ValidationException('payment.amount deve ser inteiro positivo em centavos.');
        }

        $captureType = strtolower((string) ($payment['captureType'] ?? $payment['CaptureType'] ?? ''));
        if (!in_array($captureType, ['ac', 'pa'], true)) {
            throw new ValidationException("payment.captureType deve ser 'ac' ou 'pa'.");
        }

        $transactionType = strtolower((string) ($payment['transactionType'] ?? $payment['TransactionType'] ?? ''));
        if (!in_array($transactionType, ['credit', 'debit'], true)) {
            throw new ValidationException("payment.transactionType deve ser 'credit' ou 'debit'.");
        }

        if (empty($cardInfo['numberToken']) && empty($cardInfo['vaultId']) && 
            empty($cardInfo['NumberToken']) && empty($cardInfo['VaultId'])) {
            throw new ValidationException('Informe cardInfo.numberToken OU cardInfo.vaultId.', [
                'cardInfo' => 'numberToken ou vaultId é obrigatório.',
            ]);
        }

        if (isset($cardInfo['expirationMonth'], $cardInfo['expirationYear'])
            && !Validator::validateExpirationDate($cardInfo['expirationMonth'], $cardInfo['expirationYear'])
        ) {
            throw new ValidationException('Data de expiração do cartão inválida.');
        }

        $customer = Helper::get($data, 'customer', []);
        if (!empty($customer['email']) && !Validator::validateEmail($customer['email'])) {
            throw new ValidationException('customer.email inválido.');
        }
        if (!empty($customer['documentType']) && !empty($customer['documentNumber'])) {
            $doc = (string) $customer['documentNumber'];
            $type = strtolower((string) $customer['documentType']);
            if ($type === 'cpf' && !Validator::validateCPF($doc)) {
                throw new ValidationException('customer.documentNumber (CPF) inválido.');
            }
            if ($type === 'cnpj' && !Validator::validateCNPJ($doc)) {
                throw new ValidationException('customer.documentNumber (CNPJ) inválido.');
            }
        }
        if (!empty($customer['ipAddress']) && !Validator::validateIpAddress($customer['ipAddress'])) {
            throw new ValidationException('customer.ipAddress inválido.');
        }
    }

    /**
     * Normaliza e organiza o body antes de enviar.
     *
     * Segue o padrão camelCase da documentação oficial ADIQ:
     *   { payment, cardInfo, customer, sellerInfo, sellers, lineItems, deviceInfo, shipTo }
     *
     * Aceita tanto camelCase quanto PascalCase no INPUT (compatibilidade),
     * mas SEMPRE envia em camelCase para a ADIQ.
     *
     * @param array $data
     * @return array
     */
    private function buildCreateBody(array $data)
    {
        $body = [];

        // Helper para pegar chaves em case-insensitive
        $pick = function (array $arr, $key) {
            // Tenta camelCase primeiro (padrão da doc), depois PascalCase, depois lowercase
            $variants = [$key, ucfirst($key), strtolower($key)];
            foreach ($variants as $v) {
                if (isset($arr[$v])) {
                    return $arr[$v];
                }
            }
            return null;
        };

        $payment = $pick($data, 'payment');
        if (is_array($payment)) {
            $body['payment'] = $this->normalizePayment($payment);
        }

        $cardInfo = $pick($data, 'cardInfo');
        if (is_array($cardInfo)) {
            $body['cardInfo'] = $this->normalizeCardInfo($cardInfo);
        }

        $customer = $pick($data, 'customer');
        if (is_array($customer)) {
            $body['customer'] = $this->normalizeCustomer($customer);
        }

        $sellerInfo = $pick($data, 'sellerInfo');
        if (is_array($sellerInfo)) {
            $body['sellerInfo'] = $this->normalizeSellerInfo($sellerInfo);
        }

        $sellers = $pick($data, 'sellers');
        if (is_array($sellers) && !empty($sellers)) {
            $body['sellers'] = $sellers;
        }

        $lineItems = $pick($data, 'lineItems');
        if (is_array($lineItems) && !empty($lineItems)) {
            $body['lineItems'] = $lineItems;
        }

        $deviceInfo = $pick($data, 'deviceInfo');
        if (is_array($deviceInfo) && !empty($deviceInfo)) {
            $body['deviceInfo'] = $this->normalizeDeviceInfo($deviceInfo);
        }

        $shipTo = $pick($data, 'shipTo');
        if (is_array($shipTo) && !empty($shipTo)) {
            $body['shipTo'] = $this->normalizeShipTo($shipTo);
        }

        // Se code3DS foi passado na raiz, move para sellerInfo conforme doc oficial
        $code3DS = $pick($data, 'code3DS') ?: $pick($data, 'code3ds');
        if ($code3DS) {
            if (!isset($body['sellerInfo'])) {
                $body['sellerInfo'] = [];
            }
            $body['sellerInfo']['code3DS'] = $code3DS;
        }

        return $body;
    }

    /**
     * Normaliza bloco "payment" para camelCase conforme doc ADIQ.
     *
     * Campos: transactionType, amount, currencyCode, productType, installments,
     *         captureType, recurrent, recurrentNridElo, recurrentAmountElo, isEfx
     */
    private function normalizePayment(array $payment)
    {
        $norm = $this->lowerCamelKeys($payment);

        // Valores normalizados (lowercase nos enums)
        if (isset($norm['transactionType'])) {
            $norm['transactionType'] = strtolower((string) $norm['transactionType']);
        }
        if (isset($norm['currencyCode'])) {
            $norm['currencyCode'] = strtolower((string) $norm['currencyCode']);
        }
        if (isset($norm['productType'])) {
            $norm['productType'] = strtolower((string) $norm['productType']);
        }
        if (isset($norm['captureType'])) {
            $norm['captureType'] = strtolower((string) $norm['captureType']);
        }
        if (isset($norm['amount'])) {
            $norm['amount'] = (int) $norm['amount'];
        }
        if (isset($norm['installments'])) {
            $norm['installments'] = (int) $norm['installments'];
        }
        if (isset($norm['recurrent'])) {
            $norm['recurrent'] = (bool) $norm['recurrent'];
        }
        if (isset($norm['isEfx'])) {
            $norm['isEfx'] = (bool) $norm['isEfx'];
        }
        if (isset($norm['recurrentAmountElo'])) {
            $norm['recurrentAmountElo'] = (int) $norm['recurrentAmountElo'];
        }

        return $norm;
    }

    /**
     * Normaliza bloco "cardInfo" para camelCase conforme doc ADIQ.
     *
     * Campos: numberToken, vaultId, cardholderName, securityCode, brand,
     *         expirationMonth, expirationYear, tokenDeviceId
     */
    private function normalizeCardInfo(array $cardInfo)
    {
        $norm = $this->lowerCamelKeys($cardInfo);

        if (isset($norm['brand'])) {
            $norm['brand'] = strtolower((string) $norm['brand']);
        }
        if (isset($norm['expirationMonth'])) {
            $norm['expirationMonth'] = str_pad((string) $norm['expirationMonth'], 2, '0', STR_PAD_LEFT);
        }
        if (isset($norm['expirationYear'])) {
            $year = (string) $norm['expirationYear'];
            // Mantém formato YY (2 dígitos) conforme doc
            if (strlen($year) === 4) {
                $year = substr($year, 2);
            }
            $norm['expirationYear'] = str_pad($year, 2, '0', STR_PAD_LEFT);
        }

        return $norm;
    }

    /**
     * Normaliza bloco "sellerInfo" mantendo os campos exatos da doc:
     *   orderNumber, softDescriptor, dynamicMcc, code3DS, urlSite3DS,
     *   codeAntiFraud, merchantCs, ThreeDsDataOnly,
     *   (External 3DS) cavvUcaf, xid, eci, programProtocol
     */
    private function normalizeSellerInfo(array $sellerInfo)
    {
        $norm = $this->lowerCamelKeys($sellerInfo);

        // code3DS é EXCEÇÃO: a doc usa "code3DS" (DS em maiúsculas)
        // Se vier como "code3ds" ou "code3Ds", normaliza
        foreach (['code3ds', 'code3Ds', 'Code3DS', 'Code3ds'] as $variant) {
            if (isset($norm[$variant])) {
                $norm['code3DS'] = $norm[$variant];
                unset($norm[$variant]);
            }
        }

        // urlSite3DS também
        foreach (['urlSite3ds', 'urlSite3Ds', 'UrlSite3DS', 'UrlSite3ds'] as $variant) {
            if (isset($norm[$variant])) {
                $norm['urlSite3DS'] = $norm[$variant];
                unset($norm[$variant]);
            }
        }

        // ThreeDsDataOnly mantém PascalCase conforme doc oficial
        foreach (['threeDsDataOnly', 'threedsdataonly'] as $variant) {
            if (isset($norm[$variant])) {
                $norm['ThreeDsDataOnly'] = (bool) $norm[$variant];
                unset($norm[$variant]);
            }
        }

        if (isset($norm['dynamicMcc'])) {
            $norm['dynamicMcc'] = (int) $norm['dynamicMcc'];
        }

        return $norm;
    }

    /**
     * Normaliza bloco "customer" para camelCase.
     *
     * Campos: documentType, documentNumber, firstName, lastName, email,
     *         phoneNumber, mobilePhoneNumber, address, addressNumber,
     *         complement, city, state, zipCode, ipAddress, country
     */
    private function normalizeCustomer(array $customer)
    {
        $norm = $this->lowerCamelKeys($customer);

        if (isset($norm['documentType'])) {
            $norm['documentType'] = strtolower((string) $norm['documentType']);
        }

        return $norm;
    }

    /**
     * Normaliza bloco "shipTo" para camelCase.
     */
    private function normalizeShipTo(array $shipTo)
    {
        return $this->lowerCamelKeys($shipTo);
    }

    /**
     * Normaliza bloco "deviceInfo" para camelCase conforme doc 3DS:
     *   httpAcceptBrowserValue, httpAcceptContent, httpBrowserLanguage,
     *   httpBrowserJavaEnabled, httpBrowserJavaScriptEnabled, httpBrowserColorDepth,
     *   httpBrowserScreenHeight, httpBrowserScreenWidth, httpBrowserTimeDifference,
     *   userAgentBrowserValue, deviceChannel
     */
    private function normalizeDeviceInfo(array $deviceInfo)
    {
        return $this->lowerCamelKeys($deviceInfo);
    }

    /**
     * Converte chaves de array de PascalCase para camelCase (primeira letra minúscula).
     * Mantém o resto da chave intacto.
     *
     * Ex: 'TransactionType' → 'transactionType'
     *     'transactionType' → 'transactionType' (no-op)
     *     'code3DS' → 'code3DS' (no-op, já começa com letra minúscula)
     */
    private function lowerCamelKeys(array $arr)
    {
        $out = [];
        foreach ($arr as $key => $value) {
            if (is_string($key) && strlen($key) > 0) {
                $newKey = lcfirst($key);
                $out[$newKey] = $value;
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /**
     * @param string $value
     * @param string $field
     * @throws ValidationException
     */
    private function assertId($value, $field)
    {
        if (empty($value) || !is_string($value)) {
            throw new ValidationException($field . ' é obrigatório.');
        }
    }
}
