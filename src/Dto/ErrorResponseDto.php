<?php

namespace Adiq\Dto;

use Adiq\Utils\ErrorMapper;

/**
 * Resposta de erro retornada pela ADIQ.
 *
 * Estrutura esperada (array com um ou mais erros):
 * [
 *   {
 *     "tag": "51",
 *     "description": "51-TRANSACAO NAO AUTORIZADA...",
 *     "merchantAdviceCode": "24",
 *     "brand": "Mastercard"
 *   }
 * ]
 *
 * Ou validação:
 * [
 *   {
 *     "tag": "payment.amount",
 *     "description": "O Valor informado deverá ser maior que ZERO."
 *   }
 * ]
 */
class ErrorResponseDto extends ResponseDto
{
    /**
     * Retorna o primeiro erro (mais comum ter apenas um).
     *
     * @param int $index Índice do erro (default 0)
     * @return array|null
     */
    public function getError($index = 0)
    {
        $errors = $this->body;
        if (!is_array($errors) || !isset($errors[$index])) {
            return null;
        }
        return $errors[$index];
    }

    /** @return string|null */
    public function getTag($index = 0)
    {
        $error = $this->getError($index);
        return $error['tag'] ?? null;
    }

    /** @return string|null */
    public function getDescription($index = 0)
    {
        $error = $this->getError($index);
        return $error['description'] ?? null;
    }

    /** @return string|null */
    public function getReturnCode($index = 0)
    {
        // Para erros de bandeira, tag = código de retorno (ex: "51")
        $tag = $this->getTag($index);
        if ($tag && is_numeric($tag)) {
            return $tag;
        }
        return null;
    }

    /** @return string|null */
    public function getBrand($index = 0)
    {
        $error = $this->getError($index);
        return $error['brand'] ?? null;
    }

    /** @return string|null Mastercard */
    public function getMerchantAdviceCode($index = 0)
    {
        $error = $this->getError($index);
        return $error['merchantAdviceCode'] ?? null;
    }

    /** @return string|null Descrição humanizada */
    public function getDescribedReason($index = 0)
    {
        return ErrorMapper::describe($this->getReturnCode($index), $this->getBrand($index));
    }

    /** @return string|null */
    public function getDescribedMac($index = 0)
    {
        return ErrorMapper::describeMac($this->getMerchantAdviceCode($index));
    }

    /** @return int Número total de erros */
    public function getErrorCount()
    {
        return is_array($this->body) ? count($this->body) : 0;
    }
}
