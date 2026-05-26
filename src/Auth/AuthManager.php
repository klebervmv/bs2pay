<?php

namespace Adiq\Auth;

use Adiq\Config\Config;
use Adiq\Exceptions\AuthException;
use Adiq\Exceptions\NetworkException;
use Adiq\Utils\Helper;
use klebervmv\EasyCurl;

/**
 * Gerenciamento do token OAuth2 (client_credentials).
 *
 * O token é cacheado em memória. Renova automaticamente quando expira ou
 * está a < 60s da expiração.
 *
 * Endpoint: POST {baseUrl}/auth/oauth2/v1/token
 * Headers : Authorization: Basic base64(clientId:clientSecret)
 * Body    : {"grantType": "client_credentials"}
 *
 * NÃO faz retry. Em caso de falha de rede, propaga NetworkException.
 */
class AuthManager
{
    const TOKEN_PATH = '/auth/oauth2/v1/token';

    /** @var Config */
    private $config;

    /** @var string|null */
    private $accessToken;

    /** @var int|null Unix timestamp de expiração */
    private $expiresAt;

    /** @var int Margem de renovação em segundos */
    private $renewalSkewSeconds = 60;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Retorna um access token válido. Gera ou renova conforme necessário.
     *
     * @return string
     * @throws AuthException|NetworkException
     */
    public function getToken()
    {
        if ($this->isTokenValid()) {
            return $this->accessToken;
        }
        return $this->renew();
    }

    /**
     * Força a renovação do token.
     *
     * @return string
     * @throws AuthException|NetworkException
     */
    public function renew()
    {
        $logger = $this->config->getLogger();
        $logger->debug('auth.renew.start', ['environment' => $this->config->getEnvironment()]);

        $token = $this->fetchToken($logger);

        $logger->info('auth.renew.success', [
            'expiresIn' => $this->expiresAt - time(),
        ]);

        return $token;
    }

    /** Invalida o token cacheado. */
    public function invalidate()
    {
        $this->accessToken = null;
        $this->expiresAt = null;
    }

    /** @return bool */
    public function isTokenValid()
    {
        if ($this->accessToken === null || $this->expiresAt === null) {
            return false;
        }
        return ($this->expiresAt - $this->renewalSkewSeconds) > time();
    }

    /**
     * Executa a chamada HTTP de obtenção do token.
     *
     * @param \Adiq\Utils\Logger $logger
     * @return string
     */
    private function fetchToken($logger)
    {
        $easyCurl = new EasyCurl(
            $this->config->getBaseUrl(),
            $this->config->getVerifySsl(),
            EasyCurl::CONTENTTYPJSON
        );

        $handle = $easyCurl->getCurlInit();
        if ($handle) {
            $timeoutMs = $this->config->getTimeout();
            @curl_setopt($handle, CURLOPT_TIMEOUT_MS, $timeoutMs);
            @curl_setopt($handle, CURLOPT_CONNECTTIMEOUT_MS, min(10000, $timeoutMs));
        }

        $basic = Helper::basicAuth($this->config->getClientId(), $this->config->getClientSecret());

        $easyCurl->render('POST', self::TOKEN_PATH, ['grantType' => 'client_credentials']);
        $easyCurl
            ->setHeader('Accept: application/json')
            ->setHeader('User-Agent: ' . $this->config->getUserAgent())
            ->setHeader('Authorization: Basic ' . $basic);

        try {
            $easyCurl->send();
        } catch (\Throwable $e) {
            throw new NetworkException('Falha de rede ao obter token: ' . $e->getMessage());
        }

        if ($err = $easyCurl->getError()) {
            throw new NetworkException('Erro de rede ao obter token: ' . $err);
        }

        $httpCode = (int) $easyCurl->getHttpCode();
        $body = $easyCurl->getResult();

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($body)
                ? (Helper::get($body, 'errorDescription')
                    ?: Helper::get($body, 'error_description')
                    ?: Helper::get($body, 'message')
                    ?: 'Falha na autenticação.')
                : (is_string($body) && $body !== '' ? $body : 'Falha na autenticação.');

            $logger->error('auth.renew.failure', null, [
                'httpCode' => $httpCode,
                'response' => is_array($body) ? $body : null,
            ]);

            throw new AuthException($message, $httpCode, is_array($body) ? $body : null);
        }

        if (!is_array($body)) {
            throw new AuthException('Resposta de autenticação não é JSON válido.', $httpCode);
        }

        $token = Helper::get($body, 'accessToken') ?: Helper::get($body, 'access_token');
        $expiresIn = Helper::get($body, 'expiresIn') ?: Helper::get($body, 'expires_in') ?: 3600;

        if (empty($token)) {
            throw new AuthException('Resposta de autenticação sem accessToken.', $httpCode, $body);
        }

        $this->accessToken = (string) $token;
        $this->expiresAt = time() + (int) $expiresIn;

        return $this->accessToken;
    }

    /** @return string|null */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /** @return int|null */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }
}
