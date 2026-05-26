<?php

namespace Adiq;

use Adiq\Auth\AuthManager;
use Adiq\Config\Config;
use Adiq\Payment\BinClient;
use Adiq\Payment\PaymentClient;
use Adiq\Payment\TokenClient;
use Adiq\Payment\VaultClient;
use Adiq\Payment\ZeroAuthClient;
use Adiq\ThreeDs\ThreeDsClient;
use Adiq\Webhook\WebhookManager;

/**
 * Entry-point do SDK BS2 Pay ADIQ.
 *
 * Uso:
 *
 *   $sdk = new \Adiq\AdiqPaymentSDK(
 *       'seu-client-id',
 *       'seu-client-secret',
 *       'sandbox',
 *       ['timeout' => 30000]
 *   );
 *
 *   $token = $sdk->tokens->create(['cardNumber' => '5155901222250004']);
 *   $payment = $sdk->payments->create([...]);
 *
 * @property-read PaymentClient   $payments
 * @property-read TokenClient     $tokens
 * @property-read VaultClient     $vault
 * @property-read ZeroAuthClient  $zeroAuth
 * @property-read BinClient       $bin
 * @property-read ThreeDsClient   $threeDs
 * @property-read WebhookManager  $webhook
 */
class AdiqPaymentSDK
{
    /** @var Config */
    private $config;

    /** @var AuthManager */
    private $authManager;

    /** @var PaymentClient */
    public $payments;

    /** @var TokenClient */
    public $tokens;

    /** @var VaultClient */
    public $vault;

    /** @var ZeroAuthClient */
    public $zeroAuth;

    /** @var BinClient */
    public $bin;

    /** @var ThreeDsClient */
    public $threeDs;

    /** @var WebhookManager */
    public $webhook;

    /**
     * @param string $clientId     OAuth2 client_id
     * @param string $clientSecret OAuth2 client_secret
     * @param string $environment  'sandbox' | 'homologation' | 'production'
     * @param array  $options      timeout (ms), verifySsl,
     *                             logger (instância de Adiq\Utils\Logger),
     *                             logSensitiveData, userAgent.
     *
     * @throws \Adiq\Exceptions\ValidationException
     */
    public function __construct($clientId, $clientSecret, $environment = 'sandbox', array $options = [])
    {
        $this->config = new Config($clientId, $clientSecret, $environment, $options);
        $this->authManager = new AuthManager($this->config);

        $this->payments = new PaymentClient($this->config, $this->authManager);
        $this->tokens = new TokenClient($this->config, $this->authManager);
        $this->vault = new VaultClient($this->config, $this->authManager);
        $this->zeroAuth = new ZeroAuthClient($this->config, $this->authManager);
        $this->bin = new BinClient($this->config, $this->authManager);
        $this->threeDs = new ThreeDsClient($this->config);
        $this->webhook = new WebhookManager($this->config, $this->authManager);
    }

    /** @return Config */
    public function getConfig()
    {
        return $this->config;
    }

    /** @return AuthManager */
    public function getAuthManager()
    {
        return $this->authManager;
    }

    /**
     * Força a renovação do token OAuth2 (útil em testes/diagnóstico).
     *
     * @return string Novo access token
     */
    public function refreshToken()
    {
        return $this->authManager->renew();
    }

    /**
     * Verifica se as credenciais funcionam (gera/renova token).
     *
     * @return bool
     */
    public function ping()
    {
        try {
            $this->authManager->renew();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
