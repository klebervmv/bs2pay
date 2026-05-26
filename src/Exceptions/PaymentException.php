<?php

namespace Adiq\Exceptions;

/**
 * Erros lógicos de pagamento (resposta da adquirente indicando recusa,
 * código de retorno de bandeira, MAC, etc).
 */
class PaymentException extends AdiqException
{
    /** @var string|null */
    protected $returnCode;

    /** @var string|null */
    protected $brand;

    /** @var string|null */
    protected $merchantAdviceCode;

    public function __construct(
        $message = 'Erro no pagamento.',
        $returnCode = null,
        $brand = null,
        $merchantAdviceCode = null,
        $httpCode = null,
        $responseBody = null,
        $errorTag = null
    ) {
        parent::__construct($message, 0, $httpCode, $responseBody, $errorTag);
        $this->returnCode = $returnCode;
        $this->brand = $brand;
        $this->merchantAdviceCode = $merchantAdviceCode;
    }

    /** @return string|null */
    public function getReturnCode()
    {
        return $this->returnCode;
    }

    /** @return string|null */
    public function getBrand()
    {
        return $this->brand;
    }

    /** @return string|null */
    public function getMerchantAdviceCode()
    {
        return $this->merchantAdviceCode;
    }
}
