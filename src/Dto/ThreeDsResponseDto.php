<?php

namespace Adiq\Dto;

/**
 * Resposta de operações relacionadas a 3DS 2.0.
 */
class ThreeDsResponseDto extends ResponseDto
{
    /** @return string|null Silent | Attempt | Challenge | Fail */
    public function getStatus()
    {
        return $this->get('status') ?: $this->get('threeDs.status');
    }

    /** @return string|null code3DS (UUID) */
    public function getCode3DS()
    {
        return $this->get('code3DS') ?: $this->get('threeDs.code3DS');
    }

    /** @return string|null URL do Access Control Server */
    public function getAcsUrl()
    {
        return $this->get('acsUrl') ?: $this->get('threeDs.acsUrl');
    }

    /** @return string|null PaReq */
    public function getPareq()
    {
        return $this->get('pareq') ?: $this->get('threeDs.pareq');
    }

    /** @return string|null */
    public function getThreeDsVersion()
    {
        return $this->get('threeDsVersion') ?: $this->get('threeDs.threeDsVersion');
    }

    /** @return string|null ECI code according to authentication status */
    public function getEci()
    {
        return $this->get('eci') ?: $this->get('threeDs.eci');
    }

    /** @return string|null Y (enrolled), N (not enrolled), U (unavailable) */
    public function getVeresEnrolled()
    {
        return $this->get('veresEnrolled') ?: $this->get('threeDs.veresEnrolled');
    }

    /** @return string|null Y (authenticated), N (not authenticated), U (unavailable), A (attempted) */
    public function getParesStatus()
    {
        return $this->get('paresStatus') ?: $this->get('threeDs.paresStatus');
    }

    /** @return string|null AUTHENTICATION_SUCCESSFUL | AUTHENTICATION_FAILED */
    public function getThreeDsStatus()
    {
        return $this->get('threeDsStatus') ?: $this->get('threeDs.threeDsStatus');
    }

    /** @return string|null Reason for failure */
    public function getReason()
    {
        return $this->get('reason') ?: $this->get('threeDs.reason');
    }

    /** @return string|null Cardholder message from issuer */
    public function getCardHolderMessage()
    {
        return $this->get('cardHolderMessage') ?: $this->get('threeDs.cardHolderMessage');
    }

    /** @return string|null Error code when DS has issues */
    public function getDirectoryServerErrorCode()
    {
        return $this->get('directoryServerErrorCode') ?: $this->get('threeDs.directoryServerErrorCode');
    }

    /** @return string|null Error description when DS has issues */
    public function getDirectoryServerErrorDescription()
    {
        return $this->get('directoryServerErrorDescription') ?: $this->get('threeDs.directoryServerErrorDescription');
    }

    /** @return string|null Transaction ID for challenge flow */
    public function getAuthenticationTransactionId()
    {
        return $this->get('authenticationTransactionId') ?: $this->get('threeDs.authenticationTransactionId');
    }

    /** @return bool */
    public function isChallenge()
    {
        return strcasecmp((string) $this->getStatus(), 'Challenge') === 0;
    }

    /** @return bool */
    public function isSilent()
    {
        return strcasecmp((string) $this->getStatus(), 'Silent') === 0;
    }

    /** @return bool */
    public function isAttempt()
    {
        return strcasecmp((string) $this->getStatus(), 'Attempt') === 0;
    }

    /** @return bool */
    public function isFail()
    {
        return strcasecmp((string) $this->getStatus(), 'Fail') === 0;
    }

    /** @return bool */
    public function isFailedChallenge()
    {
        return strcasecmp((string) $this->getStatus(), 'FailedChallenge') === 0;
    }

    /** @return bool */
    public function isDataOnly()
    {
        return strcasecmp((string) $this->getStatus(), 'DataOnly') === 0;
    }

    /** @return bool */
    public function isExternal()
    {
        return strcasecmp((string) $this->getStatus(), 'External') === 0;
    }
}
