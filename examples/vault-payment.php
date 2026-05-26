<?php

/**
 * Exemplo: armazenar cartão no Vault e usar em pagamentos one-click.
 *
 * Fluxo:
 *   1. Tokeniza o cartão (numberToken transacional).
 *   2. Envia para o Vault → obtém vaultId persistente.
 *   3. Usa vaultId em pagamento (sem precisar dos dados completos do cartão).
 */

require __DIR__ . '/../vendor/autoload.php';

use Adiq\AdiqPaymentSDK;
use Adiq\Exceptions\AdiqException;

$sdk = new AdiqPaymentSDK(
    getenv('ADIQ_CLIENT_ID') ?: 'seu-client-id',
    getenv('ADIQ_CLIENT_SECRET') ?: 'seu-client-secret',
    'sandbox'
);

try {
    // 1. Tokenizar
    $tokenResponse = $sdk->tokens->create(['cardNumber' => '5155901222250004']);
    $numberToken = $tokenResponse->getNumberToken();
    echo "Token transacional: {$numberToken}\n";

    // 2. Armazenar no Vault (com verificação Zero Auth)
    $vault = $sdk->vault->store([
        'numberToken' => $numberToken,
        'cardholderName' => 'JOSE SILVA',
        'securityCode' => '123',
        'brand' => 'mastercard',
        'expirationMonth' => '12',
        'expirationYear' => '30',
        'verifyCard' => true,
    ]);

    if (!$vault->isSuccess()) {
        echo "Falha ao armazenar no Vault.\n";
        exit(1);
    }

    $vaultId = $vault->getVaultId();
    echo "Cartão armazenado. vaultId: {$vaultId}\n";

    // 3. Pagamento usando vaultId
    $payment = $sdk->payments->create([
        'payment' => [
            'transactionType' => 'credit',
            'amount' => 2500,
            'currencyCode' => 'brl',
            'productType' => 'avista',
            'installments' => 1,
            'captureType' => 'ac',
            'recurrent' => false,
        ],
        'cardInfo' => [
            'vaultId' => $vaultId,
            'securityCode' => '123', // CVV ainda pode ser exigido
            'brand' => 'mastercard',
            'expirationMonth' => '12',
            'expirationYear' => '30',
            'cardholderName' => 'JOSE SILVA',
        ],
        'sellerInfo' => [
            'orderNumber' => 'VAULT-' . time(),
            'softDescriptor' => 'LOJA*TEST',
        ],
    ]);

    if ($payment->isApproved()) {
        echo "Pagamento via Vault aprovado!\n";
        echo "  paymentId: " . $payment->getPaymentId() . "\n";
    } else {
        echo "Pagamento recusado: " . $payment->getDescribedReason() . "\n";
    }
} catch (AdiqException $e) {
    echo "Erro: " . get_class($e) . " - " . $e->getMessage() . "\n";
    exit(1);
}
