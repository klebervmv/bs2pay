<?php

namespace Adiq\Utils;

/**
 * Logger estruturado (JSON). Mascara dados sensíveis por padrão.
 *
 * Pode ser substituído por qualquer logger PSR-3 fazendo wrapper.
 */
class Logger
{
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    /** @var array<string,int> */
    private static $levels = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
    ];

    /** @var string */
    private $minLevel;

    /** @var bool */
    private $logSensitiveData;

    /** @var string|null Caminho do arquivo (null = STDERR) */
    private $logFile;

    /** @var array Lista de chaves consideradas sensíveis (case-insensitive) */
    private static $sensitiveKeys = [
        'cardnumber', 'pan', 'numbertoken', 'securitycode', 'cvv', 'cvc',
        'clientsecret', 'authorization', 'access_token', 'accesstoken',
        'password', 'secret', 'token',
    ];

    /**
     * @param string      $minLevel
     * @param bool        $logSensitiveData
     * @param string|null $logFile
     */
    public function __construct($minLevel = self::LEVEL_INFO, $logSensitiveData = false, $logFile = null)
    {
        $this->minLevel = isset(self::$levels[$minLevel]) ? $minLevel : self::LEVEL_INFO;
        $this->logSensitiveData = (bool) $logSensitiveData;
        $this->logFile = $logFile;
    }

    /** @param string $operation @param array $context */
    public function debug($operation, array $context = [])
    {
        $this->log(self::LEVEL_DEBUG, $operation, $context);
    }

    /** @param string $operation @param array $context */
    public function info($operation, array $context = [])
    {
        $this->log(self::LEVEL_INFO, $operation, $context);
    }

    /** @param string $operation @param array $context */
    public function warning($operation, array $context = [])
    {
        $this->log(self::LEVEL_WARNING, $operation, $context);
    }

    /**
     * @param string          $operation
     * @param \Exception|null $exception
     * @param array           $context
     */
    public function error($operation, $exception = null, array $context = [])
    {
        if ($exception instanceof \Exception) {
            $context['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ];
        }
        $this->log(self::LEVEL_ERROR, $operation, $context);
    }

    /**
     * @param string $level
     * @param string $operation
     * @param array  $context
     */
    private function log($level, $operation, array $context)
    {
        if (self::$levels[$level] < self::$levels[$this->minLevel]) {
            return;
        }

        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'sdk' => 'bs2pay',
            'operation' => $operation,
            'context' => $this->logSensitiveData ? $context : $this->maskSensitive($context),
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        if ($this->logFile) {
            @file_put_contents($this->logFile, $line, FILE_APPEND);
        } else {
            @fwrite(STDERR, $line);
        }
    }

    /**
     * Mascara recursivamente chaves sensíveis.
     *
     * @param mixed $data
     * @return mixed
     */
    private function maskSensitive($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        $masked = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::$sensitiveKeys, true)) {
                if (is_string($value) && strlen($value) > 4) {
                    $masked[$key] = Helper::maskPan($value);
                } else {
                    $masked[$key] = '***';
                }
                continue;
            }
            $masked[$key] = is_array($value) ? $this->maskSensitive($value) : $value;
        }
        return $masked;
    }
}
