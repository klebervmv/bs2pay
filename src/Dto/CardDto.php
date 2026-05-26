<?php

namespace Adiq\Dto;

/**
 * Resposta de Token ou Vault.
 *
 * POST /v1/tokens/cards: { "numberToken": "..." }
 * POST /v1/vaults/cards: { "vaultId": "...", "validationCard": bool?, "message": "...", "brand": "...", "cardMasked": "..." }
 */
class CardDto extends ResponseDto
{
    /** @return string|null Token transacional para pagamento imediato (validade ~10 min) */
    public function getNumberToken()
    {
        return $this->get('numberToken');
    }

    /** @return string|null Identificador permanente no Vault */
    public function getVaultId()
    {
        return $this->get('vaultId');
    }

    /** @return bool|null Se cartão foi validado com Zero Auth */
    public function isValidationCard()
    {
        $val = $this->get('validationCard');
        return $val === null ? null : (bool) $val;
    }

    /** @return string|null Mensagem de validação ou erro */
    public function getMessage()
    {
        return $this->get('message');
    }

    /** @return string|null Visa, Mastercard, Elo, Amex */
    public function getBrand()
    {
        return $this->get('brand');
    }

    /** @return string|null Masked PAN (ex: 123456*******1234) */
    public function getCardMasked()
    {
        return $this->get('cardMasked');
    }
}
