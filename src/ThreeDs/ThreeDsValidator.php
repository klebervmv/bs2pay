<?php

namespace Adiq\ThreeDs;

use Adiq\Exceptions\ValidationException;

/**
 * Validações específicas do fluxo 3DS 2.0.
 */
class ThreeDsValidator
{
    /** @var array<string,bool> Campos esperados do deviceInfo (browser info) */
    private static $deviceInfoFields = [
        'browserAcceptHeader' => true,
        'browserColorDepth' => true,
        'browserJavaEnabled' => true,
        'browserLanguage' => true,
        'browserScreenHeight' => true,
        'browserScreenWidth' => true,
        'browserTimeZone' => true,
        'browserUserAgent' => true,
        'httpBrowserIp' => true,
    ];

    /** @var array<int,string> Status válidos retornados pelo 3DS */
    private static $validStatuses = ['Silent', 'Attempt', 'Challenge', 'Fail'];

    /**
     * Valida o bloco deviceInfo.
     *
     * @param array $deviceInfo
     * @throws ValidationException
     */
    public static function assertDeviceInfo(array $deviceInfo)
    {
        $missing = [];
        foreach (self::$deviceInfoFields as $field => $_) {
            if (!array_key_exists($field, $deviceInfo) || $deviceInfo[$field] === '' || $deviceInfo[$field] === null) {
                $missing[$field] = "Campo de deviceInfo obrigatório: {$field}";
            }
        }
        if (!empty($missing)) {
            throw new ValidationException('deviceInfo incompleto.', $missing);
        }

        if (!filter_var($deviceInfo['httpBrowserIp'], FILTER_VALIDATE_IP)) {
            throw new ValidationException('deviceInfo.httpBrowserIp inválido.');
        }
    }

    /**
     * @param string|null $status
     * @return bool
     */
    public static function isValidStatus($status)
    {
        if ($status === null) {
            return false;
        }
        foreach (self::$validStatuses as $valid) {
            if (strcasecmp($status, $valid) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<int,string>
     */
    public static function getValidStatuses()
    {
        return self::$validStatuses;
    }
}
