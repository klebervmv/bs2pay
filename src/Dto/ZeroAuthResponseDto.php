<?php

namespace Adiq\Dto;

/**
 * Resposta de Zero Auth (POST /v1/zero-auth).
 *
 * Resposta de sucesso (Status 200, cartão válido):
 * {
 *   "brand": "Visa",
 *   "cardNumber": "1234********4644",
 *   "transactionType": "credit",
 *   "cardAuthSuccess": true
 * }
 *
 * Resposta de sucesso (Status 200, cartão inválido):
 * {
 *   "brand": "Visa",
 *   "cardNumber": "1234********4644",
 *   "transactionType": "credit",
 *   "cardAuthSuccess": false,
 *   "message": "59 CRT SUSPENSO-59"
 * }
 *
 * Códigos de resposta possíveis em "message":
 *   14 - CARTAO INVAL.
 *   05 - LIGAR EMISSOR
 *   91 - EMISS. INDISP
 *   43 - CRT BLOQUEADO
 *   41 - CRT BLOQUEADO
 *   59 - CRT SUSPENSO
 */
class ZeroAuthResponseDto extends ResponseDto
{
    /** @return string|null Visa, Mastercard, Amex, Elo */
    public function getBrand()
    {
        return $this->get('brand');
    }

    /** @return string|null Masked card number (ex: "1234********4644") */
    public function getCardNumber()
    {
        return $this->get('cardNumber');
    }

    /** @return string|null credit, debit */
    public function getTransactionType()
    {
        return $this->get('transactionType');
    }

    /**
     * Indica se o cartão é válido (autenticação Zero Auth bem-sucedida).
     *
     * @return bool
     */
    public function isCardValid()
    {
        return (bool) $this->get('cardAuthSuccess');
    }

    /**
     * Alias para isCardValid() seguindo convenção.
     *
     * @return bool
     */
    public function getCardAuthSuccess()
    {
        return $this->isCardValid();
    }

    /**
     * Mensagem de erro retornada pelo emissor quando cartão é inválido.
     * Ex: "59 CRT SUSPENSO-59"
     *
     * @return string|null
     */
    public function getMessage()
    {
        return $this->get('message');
    }

    /**
     * Extrai o código de retorno do início da mensagem (ex: "59" de "59 CRT SUSPENSO-59").
     *
     * @return string|null
     */
    public function getReturnCode()
    {
        $message = $this->getMessage();
        if (!$message) {
            return null;
        }
        if (preg_match('/^(\d{2,3})\s/', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
