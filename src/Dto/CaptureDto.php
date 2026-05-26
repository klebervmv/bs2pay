<?php

namespace Adiq\Dto;

/**
 * Resposta de captura tardia (late capture).
 *
 * PUT /v1/payments/{paymentId}/capture retorna:
 * {
 *   "captureAuthorization": {
 *     "returnCode": "0",
 *     "description": "Capturado",
 *     "paymentId": "...",
 *     "authorizationCode": "...",
 *     "amount": 544,
 *     "releaseAt": "2026-05-14T15:52:49.6170956-03:00"
 *   }
 * }
 */
class CaptureDto extends ResponseDto
{
    /** @return bool True se captura foi bem-sucedida (returnCode = "0") */
    public function isSuccess()
    {
        return $this->getReturnCode() === '0' || $this->getReturnCode() === 0;
    }

    /** @return string|null Return code from issuer */
    public function getReturnCode()
    {
        return $this->get('captureAuthorization.returnCode');
    }

    /** @return string|null Human-readable description */
    public function getDescription()
    {
        return $this->get('captureAuthorization.description');
    }

    /** @return string|null Payment ID (TID) */
    public function getPaymentId()
    {
        return $this->get('captureAuthorization.paymentId');
    }

    /** @return string|null Authorization code from issuer */
    public function getAuthorizationCode()
    {
        return $this->get('captureAuthorization.authorizationCode');
    }

    /** @return int|null Amount captured in cents */
    public function getAmount()
    {
        $amount = $this->get('captureAuthorization.amount');
        return $amount === null ? null : (int) $amount;
    }

    /** @return string|null ISO 8601 capture registration datetime */
    public function getReleaseAt()
    {
        return $this->get('captureAuthorization.releaseAt');
    }
}
