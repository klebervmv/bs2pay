<?php

/**
 * Exemplo: pagamento básico (autorização + captura no mesmo passo).
 *
 * Fluxo:
 *   1. Tokenizar o cartão (numberToken transacional, válido por ~10 min)
 *   2. Criar pagamento com captureType='ac' (authorize + capture)
 *   3. Verificar resultado
 */

require __DIR__ . '/../vendor/autoload.php';

use Adiq\AdiqPaymentSDK;
use Adiq\Exceptions\AdiqException;
use Adiq\Exceptions\PaymentException;
use Adiq\Exceptions\ValidationException;

$clientId = getenv('ADIQ_CLIENT_ID') ?: 'seu-client-id';
$clientSecret = getenv('ADIQ_CLIENT_SECRET') ?: 'seu-client-secret';

$sdk = new AdiqPaymentSDK($clientId, $clientSecret, 'sandbox', [
    'timeout' => 30000,
]);

try {
    // 1. Tokeniza o cartão
    $tokenResponse = $sdk->tokens->create([
        'cardNumber' => '5155901222250004',
    ]);

    if (!$tokenResponse->isSuccess()) {
        echo "Falha ao tokenizar cartão.\n";
        exit(1);
    }

    $numberToken = $tokenResponse->getNumberToken();
    echo "Token gerado: {$numberToken}\n";

    // 2. Cria pagamento
    $payment = $sdk->payments->create([
        'payment' => [
            'transactionType' => 'credit',
            'amount' => 1000,            // R$ 10,00
            'currencyCode' => 'brl',
            'productType' => 'avista',
            'installments' => 1,
            'captureType' => 'ac',
            'recurrent' => false,
        ],
        'cardInfo' => [
            'numberToken' => $numberToken,
            'cardholderName' => 'JOSE SILVA',
            'securityCode' => '123',
            'brand' => 'mastercard',
            'expirationMonth' => '12',
            'expirationYear' => '30',
        ],
        'customer' => [
            'firstName' => 'Jose',
            'lastName' => 'Silva',
            'email' => 'jose@example.com',
            'documentType' => 'cpf',
            'documentNumber' => '51115672088',
            'phoneNumber' => '1122542454',
            'mobilePhoneNumber' => '11987683332',
            'address' => 'Rua Test',
            'addressNumber' => '134',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zipCode' => '09876098',
            'country' => 'BR',
            'ipAddress' => '192.168.1.1',
        ],
        'sellerInfo' => [
            'orderNumber' => 'ORDER-' . time(),
            'softDescriptor' => 'LOJA*TEST',
        ],
    ]);

    if ($payment->isApproved()) {
        echo "Pagamento aprovado!\n";
        echo "  paymentId  : " . $payment->getPaymentId() . "\n";
        echo "  authCode   : " . $payment->getAuthorizationCode() . "\n";
        echo "  orderNumber: " . $payment->getOrderNumber() . "\n";
        echo "  amount     : " . $payment->getAmount() . " (centavos)\n";
    } else {
        echo "Pagamento NÃO aprovado.\n";
        echo "  returnCode : " . $payment->getReturnCode() . "\n";
        echo "  description: " . $payment->getDescription() . "\n";
        echo "  reason     : " . $payment->getDescribedReason() . "\n";
    }
} catch (ValidationException $e) {
    echo "Erro de validação: " . $e->getMessage() . "\n";
    foreach ($e->getErrors() as $field => $msg) {
        echo "  - {$field}: {$msg}\n";
    }
    exit(1);
} catch (PaymentException $e) {
    echo "Erro no pagamento (recusa): " . $e->getMessage() . "\n";
    echo "  Bandeira: " . $e->getBrand() . " | RC: " . $e->getReturnCode() . "\n";
    if ($mac = $e->getMerchantAdviceCode()) {
        echo "  MAC: {$mac}\n";
    }
    exit(2);
} catch (AdiqException $e) {
    echo "Erro ADIQ (" . get_class($e) . "): " . $e->getMessage() . "\n";
    exit(3);
}
