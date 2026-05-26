<?php

namespace Adiq\Utils;

/**
 * Funções utilitárias gerais.
 */
class Helper
{
    /**
     * Remove caracteres não numéricos de uma string.
     *
     * @param string|null $value
     * @return string
     */
    public static function onlyDigits($value)
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    /**
     * Gera UUID v4 (para code3DS, idempotência, etc).
     *
     * @return string
     */
    public static function uuidV4()
    {
        $data = function_exists('random_bytes')
            ? random_bytes(16)
            : openssl_random_pseudo_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Mascara um PAN, mostrando apenas os 4 primeiros e 4 últimos.
     *
     * @param string $pan
     * @return string
     */
    public static function maskPan($pan)
    {
        $pan = self::onlyDigits($pan);
        $len = strlen($pan);
        if ($len < 8) {
            return str_repeat('*', $len);
        }
        return substr($pan, 0, 4) . str_repeat('*', $len - 8) . substr($pan, -4);
    }

    /**
     * Encoda Basic Auth (clientId:clientSecret) em base64.
     *
     * @param string $clientId
     * @param string $clientSecret
     * @return string
     */
    public static function basicAuth($clientId, $clientSecret)
    {
        return base64_encode($clientId . ':' . $clientSecret);
    }

    /**
     * Converte valor decimal (reais) para centavos (int).
     *
     * @param float|int|string $amount
     * @return int
     */
    public static function toCents($amount)
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Converte centavos (int) para decimal (reais).
     *
     * @param int $cents
     * @return float
     */
    public static function fromCents($cents)
    {
        return ((int) $cents) / 100;
    }

    /**
     * Retorna data atual no formato YYYYMMDD (usado pela consulta orderNumber).
     *
     * @param int|null $timestamp
     * @return string
     */
    public static function dateYmd($timestamp = null)
    {
        return date('Ymd', $timestamp === null ? time() : $timestamp);
    }

    /**
     * Decodifica JSON com tratamento de erro.
     *
     * @param string $json
     * @param bool   $assoc
     * @return mixed|null
     */
    public static function jsonDecode($json, $assoc = true)
    {
        if (!is_string($json) || $json === '') {
            return null;
        }
        $decoded = json_decode($json, $assoc);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $decoded;
    }

    /**
     * Codifica JSON com flags seguras.
     *
     * @param mixed $data
     * @return string
     */
    public static function jsonEncode($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Acessa chave aninhada em array por caminho ("a.b.c").
     *
     * @param array  $array
     * @param string $path
     * @param mixed  $default
     * @return mixed
     */
    public static function get(array $array, $path, $default = null)
    {
        $keys = explode('.', $path);
        $value = $array;
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }
        return $value;
    }
}
