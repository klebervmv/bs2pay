<?php

/**
 * Exemplo: pagamento com 3DS 2.0 integrado.
 *
 * Fluxo:
 *   1. Frontend coleta browser data (colorDepth, language, screen, etc) e envia ao backend.
 *   2. Backend gera code3DS (UUID) e monta deviceInfo + threeDs.
 *   3. POST /v1/payments com captureType='ac' + threeDs.
 *   4. Se status = Silent/Attempt → autorizado direto.
 *      Se status = Challenge → redireciona cliente para acsUrl com pareq.
 *      Se status = Fail      → recusado.
 *
 * Este script é o backend; o frontend deve enviar os dados em $_POST.
 */

require __DIR__ . '/../vendor/autoload.php';

use Adiq\AdiqPaymentSDK;
use Adiq\Exceptions\AdiqException;
use Adiq\ThreeDs\ThreeDsValidator;

$sdk = new AdiqPaymentSDK(
    getenv('ADIQ_CLIENT_ID') ?: 'seu-client-id',
    getenv('ADIQ_CLIENT_SECRET') ?: 'seu-client-secret',
    'sandbox'
);

// Browser data enviado pelo frontend
$browserData = [
    'colorDepth' => isset($_POST['colorDepth']) ? (int) $_POST['colorDepth'] : 24,
    'javaEnabled' => !empty($_POST['javaEnabled']),
    'language' => isset($_POST['language']) ? $_POST['language'] : 'pt-BR',
    'screenHeight' => isset($_POST['screenHeight']) ? (int) $_POST['screenHeight'] : 768,
    'screenWidth' => isset($_POST['screenWidth']) ? (int) $_POST['screenWidth'] : 1024,
    'timeZone' => isset($_POST['timeZone']) ? (int) $_POST['timeZone'] : 180,
];

try {
    // 1. Tokeniza
    $cardNumber = isset($_POST['cardNumber']) ? $_POST['cardNumber'] : '4111111111111111';
    $tokenResponse = $sdk->tokens->create(['cardNumber' => $cardNumber]);
    $numberToken = $tokenResponse->getNumberToken();

    // 2. Monta blocos 3DS
    $code3DS = $sdk->threeDs->generateCode3DS();
    $deviceInfo = $sdk->threeDs->buildDeviceInfo($browserData, $_SERVER);
    ThreeDsValidator::assertDeviceInfo($deviceInfo);

    $threeDsBlock = $sdk->threeDs->buildThreeDsBlock(
        $code3DS,
        '01' // No preference
    );

    // 3. Cria pagamento com 3DS
    $payment = $sdk->payments->create([
        'payment' => [
            'transactionType' => 'credit',
            'amount' => 5000,
            'currencyCode' => 'brl',
            'productType' => 'avista',
            'installments' => 1,
            'captureType' => 'ac',
            'recurrent' => false,
        ],
        'cardInfo' => [
            'numberToken' => $numberToken,
            'cardholderName' => $_POST['cardholderName'] ?? 'JOSE SILVA',
            'securityCode' => $_POST['securityCode'] ?? '123',
            'brand' => $_POST['brand'] ?? 'visa',
            'expirationMonth' => $_POST['expirationMonth'] ?? '12',
            'expirationYear' => $_POST['expirationYear'] ?? '30',
        ],
        'customer' => [
            'firstName' => 'Jose',
            'lastName' => 'Silva',
            'email' => 'jose@example.com',
            'documentType' => 'cpf',
            'documentNumber' => '51115672088',
            'phoneNumber' => '1122542454',
            'address' => 'Rua Test',
            'addressNumber' => '134',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zipCode' => '09876098',
            'country' => 'BR',
            'ipAddress' => $deviceInfo['httpBrowserIp'],
        ],
        'sellerInfo' => [
            'orderNumber' => 'ORDER-3DS-' . time(),
            'softDescriptor' => 'LOJA*TEST',
        ],
        'deviceInfo' => $deviceInfo,
        'threeDs' => $threeDsBlock,
        'code3DS' => $code3DS,
    ]);

    // 4. Interpreta resultado 3DS
    $threeDsResult = $sdk->threeDs->interpretResponse($payment->getBody());

    echo "Status 3DS: " . ($threeDsResult['status'] ?? 'N/A') . "\n";

    if ($threeDsResult['challenge']) {
        // Challenge: salvar paymentId em sessão e redirecionar para acsUrl
        $_SESSION['adiq_pending_payment'] = $payment->getPaymentId();
        echo "Challenge requerido. Redirecionando para ACS:\n";
        echo "  acsUrl: " . $threeDsResult['acsUrl'] . "\n";
        echo "  pareq:  " . substr((string) $threeDsResult['pareq'], 0, 60) . "...\n";

        // Em produção:
        // header('Location: ' . $threeDsResult['acsUrl']);
        // exit;
    } elseif ($payment->isApproved()) {
        echo "Pagamento aprovado sem desafio (Silent/Attempt).\n";
        echo "  paymentId: " . $payment->getPaymentId() . "\n";
    } else {
        echo "Pagamento recusado.\n";
        echo "  motivo: " . $payment->getDescribedReason() . "\n";
    }
} catch (AdiqException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
