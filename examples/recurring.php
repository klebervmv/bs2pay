<?php

/**
 * Exemplo: pagamento recorrente.
 *
 * Visão geral:
 *   - Primeira transação:  recurrent=true (CIT - cardholder initiated)
 *     Para Elo, capture o nridElo retornado para usar nas próximas.
 *   - Transações subsequentes (MIT - merchant initiated):
 *     usar vaultId armazenado + recurrent=true + nridElo (Elo) ou apenas vaultId.
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
    // ============== Primeira transação (CIT) ==============
    $tokenResponse = $sdk->tokens->create(['cardNumber' => '6362970000457013']); // Elo de teste
    $numberToken = $tokenResponse->getNumberToken();

    $vault = $sdk->vault->store([
        'numberToken' => $numberToken,
        'cardholderName' => 'CLIENTE RECORRENTE',
        'brand' => 'elo',
        'expirationMonth' => '12',
        'expirationYear' => '30',
        'verifyCard' => false,
    ]);
    $vaultId = $vault->getVaultId();

    $first = $sdk->payments->create([
        'payment' => [
            'transactionType' => 'credit',
            'amount' => 9990,         // R$ 99,90 - mensalidade
            'currencyCode' => 'brl',
            'productType' => 'avista',
            'installments' => 1,
            'captureType' => 'ac',
            'recurrent' => true,      // Indica início de recorrência
        ],
        'cardInfo' => [
            'vaultId' => $vaultId,
            'brand' => 'elo',
            'expirationMonth' => '12',
            'expirationYear' => '30',
            'cardholderName' => 'CLIENTE RECORRENTE',
            'securityCode' => '123',
        ],
        'sellerInfo' => [
            'orderNumber' => 'SUB-001-' . time(),
            'softDescriptor' => 'SUBSCRIPTION',
        ],
    ]);

    if (!$first->isApproved()) {
        echo "Primeira cobrança recusada: " . $first->getDescribedReason() . "\n";
        exit(1);
    }

    $nridElo = $first->getNridElo(); // Salvar este valor para uso nas próximas cobranças (apenas Elo)
    echo "Primeira cobrança OK. paymentId=" . $first->getPaymentId() . " nridElo=" . ($nridElo ?: '(N/A)') . "\n";

    // ============== Cobranças subsequentes (MIT) ==============
    // Tipicamente executadas via cron mensal.
    $next = $sdk->payments->create([
        'payment' => [
            'transactionType' => 'credit',
            'amount' => 9990,
            'currencyCode' => 'brl',
            'productType' => 'avista',
            'installments' => 1,
            'captureType' => 'ac',
            'recurrent' => true,
            // Para Elo, reenviar o nridElo da primeira transação:
            'nridElo' => $nridElo,
        ],
        'cardInfo' => [
            'vaultId' => $vaultId,
            'brand' => 'elo',
            'expirationMonth' => '12',
            'expirationYear' => '30',
            'cardholderName' => 'CLIENTE RECORRENTE',
        ],
        'sellerInfo' => [
            'orderNumber' => 'SUB-002-' . time(),
            'softDescriptor' => 'SUBSCRIPTION',
        ],
    ]);

    echo $next->isApproved()
        ? "Recorrência OK. paymentId=" . $next->getPaymentId() . "\n"
        : "Recorrência recusada: " . $next->getDescribedReason() . "\n";
} catch (AdiqException $e) {
    echo "Erro: " . get_class($e) . " - " . $e->getMessage() . "\n";
    exit(1);
}
