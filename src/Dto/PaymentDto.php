<?php

namespace Adiq\Dto;

use Adiq\Utils\ErrorMapper;

/**
 * Resposta de pagamento (sucesso).
 *
 * Estrutura esperada:
 * {
 *   "paymentAuthorization": {
 *     "returnCode": "0",
 *     "description": "Sucesso",
 *     "paymentId": "...",
 *     "authorizationCode": "...",
 *     "orderNumber": "...",
 *     "expireAt": "...",
 *     "amount": 1035,
 *     "releaseAt": "...",
 *     "nridElo": "..." (opcional)
 *   }
 * }
 */
class PaymentDto extends ResponseDto
{
    // -------- Identificação --------

    /** @return string|null */
    public function getPaymentId()
    {
        return $this->get('paymentAuthorization.paymentId');
    }

    /** @return string|null */
    public function getOrderNumber()
    {
        return $this->get('paymentAuthorization.orderNumber');
    }

    /** @return string|null */
    public function getAuthorizationCode()
    {
        return $this->get('paymentAuthorization.authorizationCode');
    }

    /** @return string|null */
    public function getReturnCode()
    {
        return $this->get('paymentAuthorization.returnCode');
    }

    /** @return string|null */
    public function getDescription()
    {
        return $this->get('paymentAuthorization.description');
    }

    /** @return int|null Valor em centavos */
    public function getAmount()
    {
        $amount = $this->get('paymentAuthorization.amount');
        return $amount === null ? null : (int) $amount;
    }

    /** @return string|null ISO 8601 */
    public function getExpireAt()
    {
        return $this->get('paymentAuthorization.expireAt');
    }

    /** @return string|null ISO 8601 */
    public function getReleaseAt()
    {
        return $this->get('paymentAuthorization.releaseAt');
    }

    /** @return string|null Identificador Elo para recorrência */
    public function getNridElo()
    {
        return $this->get('paymentAuthorization.nridElo');
    }

    // -------- 3DS (se incluído na resposta) --------

    /** @return array|null */
    public function getThreeDs()
    {
        return $this->get('threeDs');
    }

    /** @return string|null Silent | Attempt | Challenge | Fail */
    public function getThreeDsStatus()
    {
        return $this->get('threeDs.status');
    }

    /** @return string|null URL para redirecionar em Challenge */
    public function getAcsUrl()
    {
        return $this->get('threeDs.acsUrl');
    }

    /** @return string|null PaReq */
    public function getPareq()
    {
        return $this->get('threeDs.pareq');
    }

    /** @return bool */
    public function isChallenge()
    {
        return strcasecmp((string) $this->getThreeDsStatus(), 'Challenge') === 0
            && !empty($this->getAcsUrl());
    }

    // -------- Status helpers --------

    /** @return bool Pagamento autorizado com sucesso (returnCode = "0") */
    public function isApproved()
    {
        if (!$this->isSuccess()) {
            return false;
        }
        $rc = $this->getReturnCode();
        return $rc === '0' || $rc === 0;
    }

    /** @return string|null */
    public function getBrand()
    {
        return $this->get('cardInfo.brand');
    }

    /** @return string|null Descrição humanizada baseada em returnCode + bandeira */
    public function getDescribedReason()
    {
        return ErrorMapper::describe($this->getReturnCode(), $this->getBrand());
    }

    /** @return string|null */
    public function getErrorDescription()
    {
        if ($this->isApproved()) {
            return null;
        }
        return $this->getDescription() ?: $this->getDescribedReason();
    }
}
