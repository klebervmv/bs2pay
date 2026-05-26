<?php

/**
 * Exemplo: configurar webhook e validar callback recebido.
 *
 * Parte 1 — configurar a URL na ADIQ (executar uma vez).
 * Parte 2 — handler que recebe os callbacks (deixar acessível na URL configurada).
 */

require __DIR__ . '/../vendor/autoload.php';

use Adiq\AdiqPaymentSDK;
use Adiq\Exceptions\AdiqException;
use Adiq\Webhook\WebhookValidator;

$sdk = new AdiqPaymentSDK(
    getenv('ADIQ_CLIENT_ID') ?: 'seu-client-id',
    getenv('ADIQ_CLIENT_SECRET') ?: 'seu-client-secret',
    'sandbox'
);

$webhookUrl = getenv('ADIQ_WEBHOOK_URL') ?: 'https://seu-dominio.com/adiq/callback';
$secret = getenv('ADIQ_WEBHOOK_SECRET') ?: 'change-me';

// ===========================================================
// Parte 1: configurar webhook (rodar uma vez)
// ===========================================================
if (PHP_SAPI === 'cli' && (($argv[1] ?? null) === 'register')) {
    try {
        $response = $sdk->webhook->register($webhookUrl, [
            'X-Custom-Token' => $secret,
        ]);
        echo $response->isSuccess()
            ? "Webhook registrado em: {$webhookUrl}\n"
            : "Falha ao registrar webhook (HTTP {$response->getHttpCode()})\n";
    } catch (AdiqException $e) {
        echo "Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
    exit(0);
}

// ===========================================================
// Parte 2: handler do callback HTTP
// ===========================================================
if (PHP_SAPI !== 'cli') {
    // Lê o corpo cru (necessário para validar assinatura HMAC)
    $rawBody = file_get_contents('php://input');
    $signature = isset($_SERVER['HTTP_X_ADIQ_SIGNATURE']) ? $_SERVER['HTTP_X_ADIQ_SIGNATURE'] : '';

    if (!WebhookValidator::verifySignature($rawBody, $signature, $secret)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }

    $payload = WebhookValidator::parsePayload($rawBody);
    if ($payload === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    // Processar o evento (exemplo)
    $eventType = $payload['eventType'] ?? 'unknown';
    $paymentId = $payload['paymentId'] ?? null;

    error_log("[ADIQ webhook] event={$eventType} paymentId={$paymentId}");

    // Sempre responder 2xx rapidamente para evitar retentativas desnecessárias
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

echo "Uso:\n";
echo "  CLI : php examples/webhook-setup.php register   (registra a URL na ADIQ)\n";
echo "  HTTP: aponte este arquivo como rota /adiq/callback no seu webserver\n";
