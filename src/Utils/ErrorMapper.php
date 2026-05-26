<?php

namespace Adiq\Utils;

/**
 * Mapeamento de códigos de retorno das bandeiras (Visa, Mastercard, Elo, Amex)
 * e do Merchant Advice Code (MAC) da Mastercard.
 */
class ErrorMapper
{
    /** @var array<string,string> Códigos genéricos comuns às bandeiras */
    private static $genericCodes = [
        '00' => 'Transação aprovada.',
        '05' => 'Não autorizada. Entre em contato com o emissor.',
        '12' => 'Transação inválida.',
        '13' => 'Valor inválido.',
        '14' => 'Cartão inválido.',
        '30' => 'Erro de formato da mensagem.',
        '41' => 'Cartão registrado como perdido.',
        '43' => 'Cartão registrado como roubado.',
        '51' => 'Saldo/crédito insuficiente.',
        '54' => 'Cartão vencido.',
        '55' => 'Senha incorreta.',
        '57' => 'Transação não permitida para este cartão.',
        '58' => 'Transação não permitida para este estabelecimento.',
        '59' => 'Suspeita de fraude.',
        '61' => 'Limite de saque excedido.',
        '62' => 'Cartão restrito.',
        '63' => 'Violação de segurança.',
        '65' => 'Limite de transações excedido.',
        '75' => 'Excesso de tentativas de senha.',
        '78' => 'Cartão novo, ainda não desbloqueado.',
        '82' => 'CVV inválido.',
        '91' => 'Emissor indisponível.',
        '96' => 'Falha no sistema da bandeira.',
    ];

    /** @var array<string,string> Códigos específicos Visa */
    private static $visaCodes = [
        '1A' => 'Strong Customer Authentication requerida (3DS).',
        'N7' => 'Falha no CVV2.',
    ];

    /** @var array<string,string> Códigos específicos Mastercard */
    private static $mastercardCodes = [
        '6P' => 'Dados de verificação do cartão inválidos.',
    ];

    /** @var array<string,string> Códigos específicos Elo */
    private static $eloCodes = [
        '5W' => 'Reenviar a transação posteriormente.',
    ];

    /** @var array<string,string> Códigos específicos Amex */
    private static $amexCodes = [
        '100' => 'Negada.',
        '116' => 'Saldo insuficiente.',
        '122' => 'CVV inválido.',
        '125' => 'Cartão inválido.',
        '181' => 'Erro do sistema.',
    ];

    /** @var array<string,string> Merchant Advice Code (Mastercard) */
    private static $macCodes = [
        '01' => 'Nova conta. Tente outro cartão.',
        '02' => 'Tente novamente em alguns minutos.',
        '03' => 'Não tente novamente. Contate o portador.',
        '04' => 'Conta cancelada. Não tente novamente.',
        '21' => 'Pagamento recorrente cancelado pelo portador.',
        '22' => 'Falha na recorrência por suspeita de fraude.',
        '24' => 'Recorrência expirada.',
    ];

    /**
     * Retorna mensagem amigável para um código de retorno + bandeira.
     *
     * @param string|null $code
     * @param string|null $brand visa|mastercard|elo|amex
     * @return string
     */
    public static function describe($code, $brand = null)
    {
        if ($code === null || $code === '') {
            return 'Código de retorno não informado.';
        }
        $code = strtoupper((string) $code);
        $brand = $brand ? strtolower((string) $brand) : null;

        switch ($brand) {
            case 'visa':
                if (isset(self::$visaCodes[$code])) {
                    return self::$visaCodes[$code];
                }
                break;
            case 'mastercard':
            case 'master':
                if (isset(self::$mastercardCodes[$code])) {
                    return self::$mastercardCodes[$code];
                }
                break;
            case 'elo':
                if (isset(self::$eloCodes[$code])) {
                    return self::$eloCodes[$code];
                }
                break;
            case 'amex':
            case 'americanexpress':
                if (isset(self::$amexCodes[$code])) {
                    return self::$amexCodes[$code];
                }
                break;
        }

        return isset(self::$genericCodes[$code])
            ? self::$genericCodes[$code]
            : sprintf('Código %s (bandeira %s) sem descrição mapeada.', $code, $brand ?: 'desconhecida');
    }

    /**
     * Descrição do Merchant Advice Code da Mastercard.
     *
     * @param string|null $mac
     * @return string|null
     */
    public static function describeMac($mac)
    {
        if ($mac === null || $mac === '') {
            return null;
        }
        $mac = strtoupper((string) $mac);
        return isset(self::$macCodes[$mac]) ? self::$macCodes[$mac] : null;
    }

    /**
     * Indica se um código de retorno permite retentativa.
     *
     * @param string|null $code
     * @return bool
     */
    public static function isRetryable($code)
    {
        if ($code === null) {
            return false;
        }
        $code = strtoupper((string) $code);
        // 91 (emissor indisponível), 96 (falha de sistema), 5W (Elo) são tipicamente retentáveis.
        return in_array($code, ['91', '96', '5W'], true);
    }
}
