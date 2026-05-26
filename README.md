# SDK PHP - BS2 Pay ADIQ E-commerce

SDK PHP completo e pronto para produção da adquirente **BS2 Pay ADIQ**, construído sobre a biblioteca HTTP [easyCurl](https://github.com/klebervmv/easyCurl).

- ✅ Compatível com **PHP 7.1 → 8.4**
- ✅ OAuth2 com cache e renovação automática de token
- ✅ **Sem retry automático** — evita risco de cobrança duplicada por timeout
- ✅ Suporte completo a **3DS 2.0** integrado
- ✅ Vault, Zero Auth, recorrência (incluindo nridElo da Elo) e marketplace
- ✅ Webhooks com validação de assinatura HMAC SHA-256 constant-time
- ✅ Logging estruturado em JSON com mascaramento automático de PAN, CVV e tokens
- ✅ Mapeamento dos códigos de retorno de Visa, Mastercard, Elo, Amex e MAC

---

## Instalação

```bash
composer require adiq/sdk-php
```

Dependências:
- `php` ≥ 7.1, com extensões `json` e `curl`
- `klebervmv/easycurl` ^1.0

---

## Configuração rápida

```php
require __DIR__ . '/vendor/autoload.php';

use Adiq\AdiqPaymentSDK;

$sdk = new AdiqPaymentSDK(
    'seu-client-id',
    'seu-client-secret',
    'sandbox',                          // sandbox | homologation | production
    [
        'timeout'    => 30000,          // ms
        'verifySsl'  => true,
        'logSensitiveData' => false,
    ]
);
```

### Ambientes

| Ambiente       | URL                                  |
|---------------|--------------------------------------|
| sandbox       | https://ecommerce-sandbox.adiq.io   |
| homologation  | https://ecommerce-hml.adiq.io       |
| production    | https://ecommerce.adiq.io           |

---

## Uso

### 1. Tokenizar cartão (uso único, ~10 min)

```php
$token = $sdk->tokens->create(['cardNumber' => '5155901222250004']);
$numberToken = $token->getNumberToken();
```

### 2. Criar pagamento (autoriza + captura)

```php
$payment = $sdk->payments->create([
    'payment' => [
        'transactionType' => 'credit',
        'amount'          => 1000,         // R$ 10,00 em centavos
        'currencyCode'    => 'brl',
        'productType'     => 'avista',
        'installments'    => 1,
        'captureType'     => 'ac',         // 'ac' = auth+capture, 'pa' = pre-auth
        'recurrent'       => false,
    ],
    'cardInfo' => [
        'numberToken'      => $numberToken,
        'cardholderName'   => 'JOSE SILVA',
        'securityCode'     => '123',
        'brand'            => 'mastercard',
        'expirationMonth'  => '12',
        'expirationYear'   => '30',
    ],
    'customer' => [
        'firstName'        => 'Jose',
        'lastName'         => 'Silva',
        'email'            => 'jose@example.com',
        'documentType'     => 'cpf',
        'documentNumber'   => '51115672088',
        // ...
    ],
    'sellerInfo' => [
        'orderNumber'      => 'ORDER-001',
        'softDescriptor'   => 'LOJA*TEST',
    ],
]);

if ($payment->isApproved()) {
    echo "Aprovado: " . $payment->getPaymentId();
} else {
    echo "Recusado: " . $payment->getDescribedReason();
}
```

### 3. Capturar pagamento autorizado (late capture)

```php
$result = $sdk->payments->capture('PAYMENT_ID', 1000); // ou null para valor total
```

### 4. Cancelar / refund

```php
$result = $sdk->payments->cancel('PAYMENT_ID');         // total
$result = $sdk->payments->cancel('PAYMENT_ID', 500);    // parcial
```

### 5. Consultar pagamento

```php
$payment = $sdk->payments->get('PAYMENT_ID', 'v2');
$payment = $sdk->payments->getByOrderNumber('ORDER-001', '20260513');
```

### 6. Vault (one-click / recorrência)

```php
$vault = $sdk->vault->store([
    'numberToken'     => $numberToken,
    'cardholderName'  => 'JOSE SILVA',
    'brand'           => 'mastercard',
    'expirationMonth' => '12',
    'expirationYear'  => '30',
    'verifyCard'      => true, // Zero Auth antes de salvar
]);
$vaultId = $vault->getVaultId();

// Usar vaultId em vez de numberToken na próxima cobrança
$payment = $sdk->payments->create([
    'payment'  => [...],
    'cardInfo' => ['vaultId' => $vaultId, ...],
    // ...
]);
```

### 7. Zero Auth (validar cartão sem cobrar)

```php
$result = $sdk->zeroAuth->verify(['vaultId' => $vaultId]);
```

### 8. Consulta BIN

```php
$bin = $sdk->bin->search('515590');
// $bin->getBody() retorna { bank, brand, country, type, ... }
```

### 9. 3DS 2.0 integrado

```php
$code3DS = $sdk->threeDs->generateCode3DS();

$deviceInfo = $sdk->threeDs->buildDeviceInfo([
    'colorDepth'   => 24,
    'javaEnabled'  => false,
    'language'     => 'pt-BR',
    'screenHeight' => 1080,
    'screenWidth'  => 1920,
    'timeZone'     => 180,
]);

$threeDs = $sdk->threeDs->buildThreeDsBlock($code3DS, '01');

$payment = $sdk->payments->create([
    'payment'    => [...],
    'cardInfo'   => [...],
    'customer'   => [...],
    'sellerInfo' => [...],
    'deviceInfo' => $deviceInfo,
    'threeDs'    => $threeDs,
    'code3DS'    => $code3DS,
]);

$result = $sdk->threeDs->interpretResponse($payment->getBody());
if ($result['challenge']) {
    // Redirecionar para $result['acsUrl'] com $result['pareq']
}
```

### 10. Webhook

```php
// CLI: registrar URL de callback (rodar uma vez)
$sdk->webhook->register('https://seudominio.com/adiq/callback', [
    'X-Custom-Token' => 'segredo-compartilhado',
]);

// Handler HTTP
use Adiq\Webhook\WebhookValidator;

$rawBody   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_ADIQ_SIGNATURE'] ?? '';

if (!WebhookValidator::verifySignature($rawBody, $signature, $secret)) {
    http_response_code(401); exit;
}

$payload = WebhookValidator::parsePayload($rawBody);
// ... processar
```

---

## Tratamento de erros

Todas as exceptions herdam de `Adiq\Exceptions\AdiqException`:

| Exception              | Quando ocorre                                          |
|-----------------------|--------------------------------------------------------|
| `ValidationException`  | Validação local OU HTTP 400                            |
| `AuthException`        | HTTP 401 / 403, falha de OAuth                         |
| `NotFoundException`    | HTTP 404                                                |
| `RateLimitException`   | HTTP 429 (com `getRetryAfter()`)                       |
| `PaymentException`     | Recusa de bandeira (com `getReturnCode()`, MAC, etc)   |
| `NetworkException`     | Timeout, DNS, conexão recusada, 5xx persistente        |

```php
try {
    $payment = $sdk->payments->create([...]);
} catch (\Adiq\Exceptions\ValidationException $e) {
    foreach ($e->getErrors() as $field => $msg) { /* ... */ }
} catch (\Adiq\Exceptions\PaymentException $e) {
    $rc  = $e->getReturnCode();
    $mac = $e->getMerchantAdviceCode();
} catch (\Adiq\Exceptions\AdiqException $e) {
    // Genérico
}
```

---

## Política de retry — leia antes

**O SDK não faz retry automático.** Essa é uma decisão deliberada de segurança.

Em pagamentos, um `POST /v1/payments` que falha com **timeout ou 5xx** pode ter sido processado com sucesso pelo servidor — apenas a resposta se perdeu. Retentar automaticamente nesse caso pode debitar o cartão do cliente duas vezes.

### Como lidar com falhas incertas

1. **Use `orderNumber` único e determinístico** por pedido (em `sellerInfo.orderNumber`).
2. Em caso de `NetworkException` em `payments->create()`, **não retente cegamente**. Antes, consulte:

   ```php
   try {
       $payment = $sdk->payments->create([
           'payment'    => [...],
           'cardInfo'   => [...],
           'sellerInfo' => ['orderNumber' => 'ORDER-001', ...],
       ]);
   } catch (\Adiq\Exceptions\NetworkException $e) {
       // Verifica se a transação chegou a ser registrada na ADIQ
       try {
           $existing = $sdk->payments->getByOrderNumber('ORDER-001', date('Ymd'));
           if ($existing->isApproved()) {
               // Já aprovada — não reenvie, apenas use o paymentId retornado
           } else {
               // Não existe / falhou — seguro reenviar
           }
       } catch (\Adiq\Exceptions\NotFoundException $e) {
           // Não chegou ao servidor; seguro reenviar
       }
   }
   ```

### Quando retentar é seguro (decisão do caller)

| Operação | Seguro retentar? |
|---|---|
| GET (consultas) | ✅ Sempre — idempotente |
| DELETE vault | ✅ — idempotente |
| `POST /v1/tokens/cards` | ⚠️ Baixo risco — não cobra |
| `POST /v1/payments` | ❌ Apenas após verificar via `getByOrderNumber` |
| `PUT capture/cancel` | ❌ "Já capturado" pode ser falso-OK |

`RateLimitException` (HTTP 429) expõe `getRetryAfter()` — você pode aguardar e reenviar manualmente.

---

## Logging

Por padrão, o SDK loga em JSON em `STDERR` com mascaramento automático de campos sensíveis (`cardNumber`, `securityCode`, `numberToken`, `clientSecret`, `authorization`, etc).

Para usar logger customizado:

```php
use Adiq\Utils\Logger;

$logger = new Logger('debug', false, __DIR__ . '/logs/adiq.log');
$sdk = new AdiqPaymentSDK($id, $secret, 'sandbox', ['logger' => $logger]);
```

---

## Estrutura

```
src/
├── AdiqPaymentSDK.php           # Entry-point
├── Config/Config.php
├── Auth/{AuthManager, CredentialsInterface}.php
├── Client/{HttpClient, Request}.php
├── Payment/{PaymentClient, TokenClient, VaultClient, ZeroAuthClient, BinClient}.php
├── ThreeDs/{ThreeDsClient, ThreeDsValidator}.php
├── Webhook/{WebhookManager, WebhookValidator}.php
├── Dto/{ResponseDto, PaymentDto, CardDto, CustomerDto, ThreeDsResponseDto, ErrorResponseDto}.php
├── Exceptions/{AdiqException, ValidationException, AuthException, PaymentException, NetworkException, NotFoundException, RateLimitException}.php
└── Utils/{Validator, Logger, ErrorMapper, Helper}.php

examples/
├── basic-payment.php
├── 3ds-flow.php
├── vault-payment.php
├── recurring.php
└── webhook-setup.php

tests/
├── integration.php        # Suite end-to-end contra sandbox/homologation
└── 3ds-challenge.html     # Helper visual para simular o challenge 3DS no browser
```

### Executando os testes

```bash
# Copie .env.example para .env e preencha CLIENT_ID/CLIENT_SECRET, então:
php tests/integration.php
```

O `tests/3ds-challenge.html` é uma página auxiliar — abra direto no browser
(via Laragon ou servidor PHP embutido) para concluir manualmente o passo de
challenge do fluxo 3DS 2.0.

---

## Boas práticas

- **Valores em centavos**: `amount` é sempre inteiro (`1000` = R$ 10,00).
- **PCI-DSS**: nunca persista PAN ou CVV. Use `tokens->create()` no momento da cobrança ou `vault->store()` para armazenar.
- **Idempotência**: use `sellerInfo.orderNumber` único por pedido — permite consulta posterior por `payments->getByOrderNumber()`.
- **Webhooks**: sempre valide a assinatura HMAC e responda 2xx rapidamente; processe em background.
- **3DS**: mesmo após captura imediata (`ac`), inclua deviceInfo + threeDs para ter liability shift.
- **Recorrência Elo**: salve `nridElo` da primeira transação para reenviar nas subsequentes.

---

## Próximos passos

- /help: `/help` na CLI Claude Code
- Feedback: https://github.com/anthropics/claude-code/issues

---

## Licença

MIT — veja [LICENSE](LICENSE).
