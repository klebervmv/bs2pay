<?php

namespace Adiq\ThreeDs;

use Adiq\Config\Config;
use Adiq\Utils\Helper;

/**
 * Helpers para o fluxo 3DS 2.0 integrado ao endpoint /v1/payments.
 *
 * O 3DS na ADIQ não tem endpoint dedicado — você envia threeDs + deviceInfo
 * dentro do POST /v1/payments e a resposta indica:
 *  - Silent: aprovado sem interação
 *  - Attempt: tentativa registrada, sem interação
 *  - Challenge: redirecione o cliente para acsUrl com pareq
 *  - Fail: falha no 3DS
 *
 * Esta classe ajuda a montar os blocos threeDs/deviceInfo e a gerar code3DS.
 */
class ThreeDsClient
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Gera um code3DS (UUID v4) para usar como identificador do fluxo 3DS.
     *
     * @return string
     */
    public function generateCode3DS()
    {
        return Helper::uuidV4();
    }

    /**
     * Monta o bloco deviceInfo a partir de variáveis $_SERVER / $_REQUEST
     * de uma requisição web vinda do navegador do comprador.
     *
     * As chamadas frontend (JS) devem coletar e enviar:
     *  - colorDepth (window.screen.colorDepth)
     *  - javaEnabled (navigator.javaEnabled())
     *  - language (navigator.language)
     *  - screenHeight / screenWidth
     *  - timeZone (new Date().getTimezoneOffset())
     *
     * @param array $browserData {
     *   colorDepth: int,
     *   javaEnabled: bool,
     *   language: string,
     *   screenHeight: int,
     *   screenWidth: int,
     *   timeZone: int,
     * }
     * @param array $server $_SERVER (opcional, default = $_SERVER)
     * @return array
     */
    public function buildDeviceInfo(array $browserData, array $server = null)
    {
        $server = $server === null ? (isset($_SERVER) ? $_SERVER : []) : $server;

        return [
            'browserAcceptHeader' => isset($server['HTTP_ACCEPT']) ? $server['HTTP_ACCEPT'] : 'application/json',
            'browserColorDepth' => (string) (isset($browserData['colorDepth']) ? $browserData['colorDepth'] : '24'),
            'browserJavaEnabled' => isset($browserData['javaEnabled']) ? ($browserData['javaEnabled'] ? 'true' : 'false') : 'false',
            'browserLanguage' => isset($browserData['language']) ? $browserData['language'] : 'pt-BR',
            'browserScreenHeight' => (string) (isset($browserData['screenHeight']) ? $browserData['screenHeight'] : '768'),
            'browserScreenWidth' => (string) (isset($browserData['screenWidth']) ? $browserData['screenWidth'] : '1024'),
            'browserTimeZone' => (string) (isset($browserData['timeZone']) ? $browserData['timeZone'] : '180'),
            'browserUserAgent' => isset($server['HTTP_USER_AGENT']) ? $server['HTTP_USER_AGENT'] : 'unknown',
            'httpBrowserIp' => $this->extractClientIp($server),
        ];
    }

    /**
     * Monta o bloco threeDs.
     *
     * @param string      $code3DS             UUID gerado por generateCode3DS()
     * @param string      $authenticationType  '01' (no preference), '02' (no challenge), '03' (challenge requested), etc.
     * @param array       $extras              Outros campos opcionais (cardholderInfo, merchantRiskIndicator, etc)
     * @return array
     */
    public function buildThreeDsBlock($code3DS, $authenticationType = '01', array $extras = [])
    {
        $base = [
            'code3DS' => $code3DS,
            'authenticationType' => $authenticationType,
        ];
        return array_merge($base, $extras);
    }

    /**
     * Interpreta o resultado de 3DS de uma resposta de pagamento.
     *
     * @param array $paymentResponseBody
     * @return array{status:string|null,challenge:bool,acsUrl:string|null,pareq:string|null}
     */
    public function interpretResponse(array $paymentResponseBody)
    {
        $status = Helper::get($paymentResponseBody, 'threeDs.status');
        $acsUrl = Helper::get($paymentResponseBody, 'threeDs.acsUrl');
        $pareq = Helper::get($paymentResponseBody, 'threeDs.pareq');

        return [
            'status' => $status,
            'challenge' => is_string($status) && strcasecmp($status, 'Challenge') === 0 && !empty($acsUrl),
            'acsUrl' => $acsUrl,
            'pareq' => $pareq,
        ];
    }

    /**
     * Extrai IP do cliente respeitando X-Forwarded-For quando aplicável.
     *
     * @param array $server
     * @return string
     */
    private function extractClientIp(array $server)
    {
        $candidates = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($candidates as $key) {
            if (!empty($server[$key])) {
                $ip = trim(explode(',', $server[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
