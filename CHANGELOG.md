# Changelog

Todas as mudanças relevantes deste SDK são documentadas aqui.
Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
versionamento conforme [SemVer](https://semver.org/lang/pt-BR/).

## [1.1.0] - 2026-05-27

### Adicionado
- `vault->store()` agora aceita `platformType` (`'credit'` | `'debit'`), com default `'credit'`. Compatível retroativamente para integrações que diferenciam vault de crédito x débito.

### Alterado
- Removida a constante `AdiqPaymentSDK::VERSION` (não utilizada — versão passou a ser gerenciada via tag git).
- Removido o campo `"version"` hardcoded do `composer.json` (Packagist lê da tag git).
- User-Agent default simplificado de `adiq-sdk-php/1.0.0` para `adiq-sdk`.
- Campo `sdk` no log estruturado: `adiq-sdk-php` → `adiq-sdk`.

### Organização
- Arquivos de teste movidos para `tests/` (`tests/integration.php` e `tests/3ds-challenge.html`).
- README atualizado com seção de execução dos testes.

## [1.0.0] - 2026-05-13

### Adicionado
- Estrutura inicial do SDK (PSR-4 `Adiq\`).
- `AdiqPaymentSDK` como entry-point com clients agregados.
- `Config` com suporte a sandbox / homologation / production.
- `AuthManager` OAuth2 client_credentials com cache e renovação automática.
- `HttpClient` baseado em easyCurl. **Sem retry automático** — uma decisão de segurança para evitar cobrança duplicada quando a resposta de um POST se perde após processamento no servidor. O caller deve consultar `payments->getByOrderNumber()` antes de reenviar.
- `PaymentClient`: create, capture (late capture), cancel (refund), get (v1/v2), getByOrderNumber.
- `TokenClient`: tokenização transacional de cartão (`/v1/tokens/cards`).
- `VaultClient`: armazenamento, consulta e remoção em Vault (`/v1/vaults/cards`).
- `ZeroAuthClient`: validação de cartão sem cobrança.
- `BinClient`: consulta BIN.
- `ThreeDsClient`: helpers para 3DS 2.0 integrado (geração de code3DS, montagem de deviceInfo e threeDs, interpretação da resposta).
- `WebhookManager` + `WebhookValidator` com HMAC SHA-256 constant-time.
- DTOs de resposta tipados (`PaymentDto`, `CardDto`, `ThreeDsResponseDto`, `ErrorResponseDto`, `ResponseDto`) e DTO de input (`CustomerDto`).
- Exceptions: `AdiqException`, `ValidationException`, `AuthException`, `PaymentException`, `NetworkException`, `NotFoundException`, `RateLimitException`.
- Utilitários: `Validator` (Luhn, CPF, CNPJ, e-mail, IP, expiração, amount), `Helper` (UUID v4, mask PAN, Basic Auth, JSON), `Logger` (estruturado JSON com mascaramento), `ErrorMapper` (códigos Visa/Mastercard/Elo/Amex + MAC).
- Exemplos: pagamento básico, fluxo 3DS, vault, recorrência, webhook.
- Compatibilidade: PHP 7.1 → 8.4.
