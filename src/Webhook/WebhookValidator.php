<?php

namespace Adiq\Webhook;

/**
 * Helpers para validar webhooks recebidos da ADIQ no seu servidor.
 *
 * Inclui comparação constant-time de assinatura HMAC e parsing do payload.
 */
class WebhookValidator
{
    /**
     * Valida assinatura HMAC SHA-256.
     *
     * @param string $rawBody         Corpo cru do request (raw, sem decodificar)
     * @param string $signatureHeader Conteúdo do header X-Adiq-Signature (ou nome customizado)
     * @param string $secret          Segredo compartilhado para o webhook
     * @return bool
     */
    public static function verifySignature($rawBody, $signatureHeader, $secret)
    {
        if (empty($rawBody) || empty($signatureHeader) || empty($secret)) {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        $provided = self::normalizeSignature($signatureHeader);

        return self::hashEquals($expected, $provided);
    }

    /**
     * Decodifica o payload do webhook.
     *
     * @param string $rawBody
     * @return array|null
     */
    public static function parsePayload($rawBody)
    {
        if (!is_string($rawBody) || $rawBody === '') {
            return null;
        }
        $decoded = json_decode($rawBody, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    /**
     * Remove prefixos comuns ("sha256=" etc) da assinatura recebida.
     *
     * @param string $signature
     * @return string
     */
    private static function normalizeSignature($signature)
    {
        $signature = trim($signature);
        if (stripos($signature, 'sha256=') === 0) {
            $signature = substr($signature, 7);
        }
        return strtolower($signature);
    }

    /**
     * Comparação constant-time. Usa hash_equals() quando disponível.
     *
     * @param string $expected
     * @param string $provided
     * @return bool
     */
    private static function hashEquals($expected, $provided)
    {
        if (function_exists('hash_equals')) {
            return hash_equals(strtolower($expected), strtolower($provided));
        }
        if (strlen($expected) !== strlen($provided)) {
            return false;
        }
        $result = 0;
        for ($i = 0; $i < strlen($expected); $i++) {
            $result |= ord($expected[$i]) ^ ord($provided[$i]);
        }
        return $result === 0;
    }
}
