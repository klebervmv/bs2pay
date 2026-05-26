<?php

namespace Adiq\Config;

use Adiq\Exceptions\ValidationException;
use Adiq\Utils\Logger;

/**
 * Configuração centralizada do SDK ADIQ.
 *
 * Mantém credenciais, URLs por ambiente, parâmetros HTTP e instância de logger.
 */
class Config
{
    const ENV_SANDBOX = 'sandbox';
    const ENV_HOMOLOGATION = 'homologation';
    const ENV_PRODUCTION = 'production';

    /** @var array<string,string> */
    private static $endpoints = [
        self::ENV_SANDBOX => 'https://ecommerce-sandbox.adiq.io',
        self::ENV_HOMOLOGATION => 'https://ecommerce-hml.adiq.io',
        self::ENV_PRODUCTION => 'https://ecommerce.adiq.io',
    ];

    /** @var string */
    private $clientId;

    /** @var string */
    private $clientSecret;

    /** @var string */
    private $environment;

    /** @var string */
    private $baseUrl;

    /** @var int Milliseconds */
    private $timeout = 30000;

    /** @var bool */
    private $verifySsl = true;

    /** @var bool */
    private $logSensitiveData = false;

    /** @var Logger|null */
    private $logger;

    /** @var string */
    private $userAgent = 'bs2pay';

    /**
     * @param string $clientId     OAuth2 Client ID
     * @param string $clientSecret OAuth2 Client Secret
     * @param string $environment  sandbox | homologation | production
     * @param array  $options      timeout (ms), verifySsl, logger, logSensitiveData, userAgent
     *
     * @throws ValidationException
     */
    public function __construct($clientId, $clientSecret, $environment = self::ENV_SANDBOX, array $options = [])
    {
        if (empty($clientId) || !is_string($clientId)) {
            throw new ValidationException('clientId é obrigatório e deve ser uma string.');
        }
        if (empty($clientSecret) || !is_string($clientSecret)) {
            throw new ValidationException('clientSecret é obrigatório e deve ser uma string.');
        }
        if (!isset(self::$endpoints[$environment])) {
            throw new ValidationException(sprintf(
                'Ambiente inválido "%s". Use: %s.',
                $environment,
                implode(', ', array_keys(self::$endpoints))
            ));
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->environment = $environment;
        $this->baseUrl = self::$endpoints[$environment];

        if (isset($options['timeout'])) {
            $this->timeout = max(1000, (int) $options['timeout']);
        }
        if (isset($options['verifySsl'])) {
            $this->verifySsl = (bool) $options['verifySsl'];
        }
        if (isset($options['logSensitiveData'])) {
            $this->logSensitiveData = (bool) $options['logSensitiveData'];
        }
        if (isset($options['userAgent']) && is_string($options['userAgent'])) {
            $this->userAgent = $options['userAgent'];
        }
        if (isset($options['logger']) && $options['logger'] instanceof Logger) {
            $this->logger = $options['logger'];
        }
    }

    /** @return string */
    public function getClientId()
    {
        return $this->clientId;
    }

    /** @return string */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /** @return string */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /** @return string */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /** @return int */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /** @return bool */
    public function getVerifySsl()
    {
        return $this->verifySsl;
    }

    /** @return bool */
    public function getLogSensitiveData()
    {
        return $this->logSensitiveData;
    }

    /** @return string */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /** @return Logger */
    public function getLogger()
    {
        if ($this->logger === null) {
            $this->logger = new Logger('info', $this->logSensitiveData);
        }
        return $this->logger;
    }

    /** @return bool */
    public function isProduction()
    {
        return $this->environment === self::ENV_PRODUCTION;
    }
}
