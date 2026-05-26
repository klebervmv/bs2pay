<?php

namespace Adiq\Dto;

/**
 * Resposta de cancelamento/refund.
 *
 * PUT /v1/payments/{paymentId}/cancel retorna:
 * {
 *   "cancelAuthorization": {
 *     "returnCode": "0",
 *     "description": "Cancelado",
 *     "paymentId": "...",
 *     "authorizationCode": "...",
 *     "amount": 1023,
 *     "releaseAt": "2019-09-24T13:43:12.1952799-03:00"
 *   }
 * }
 */
class CancelDto extends ResponseDto
{
    /** @return bool True se cancelamento foi bem-sucedido (returnCode = "0") */
    public function isSuccess()
    {
        return $this->getReturnCode() === '0' || $this->getReturnCode() === 0;
    }

    /** @return string|null Return code from issuer */
    public function getReturnCode()
    {
        return $this->get('cancelAuthorization.returnCode');
    }

    /** @return string|null Human-readable description */
    public function getDescription()
    {
        return $this->get('cancelAuthorization.description');
    }

    /** @return string|null Payment ID (TID) */
    public function getPaymentId()
    {
        return $this->get('cancelAuthorization.paymentId');
    }

    /** @return string|null Cancellation authorization code */
    public function getAuthorizationCode()
    {
        return $this->get('cancelAuthorization.authorizationCode');
    }

    /** @return int|null Amount canceled in cents */
    public function getAmount()
    {
        $amount = $this->get('cancelAuthorization.amount');
        return $amount === null ? null : (int) $amount;
    }

    /** @return string|null ISO 8601 cancellation registration datetime */
    public function getReleaseAt()
    {
        return $this->get('cancelAuthorization.releaseAt');
    }
}
