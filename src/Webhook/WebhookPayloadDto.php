<?php

namespace Adiq\Webhook;

/**
 * Notificação recebida via webhook.
 *
 * Enviada para a URL registrada em POST /v1/merchant/webhook.
 * Contém informações sobre transações de autorização/captura e recorrência.
 *
 * {
 *   "Date": "2025-04-28T15:59:31.6906714",
 *   "OrderNumber": "0000000001",
 *   "PaymentId": "020057103504281858290001942369970000000000",
 *   "PaymentMethod": "Credit",
 *   "Amount": "690",
 *   "StatusCode": "0",
 *   "StatusDescription": "Captura - Sucesso",
 *   "SubscriptionId": "9d219bbf-1539-4f36-a386-80dc7dde0fc6",
 *   "BillingId": "17684803-fc4f-4441-9f57-bd5b1ed64f74"
 * }
 */
class WebhookPayloadDto
{
    /** @var array */
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /** @return string ISO 8601 datetime */
    public function getDate()
    {
        return $this->data['Date'] ?? null;
    }

    /** @return string Customer order number */
    public function getOrderNumber()
    {
        return $this->data['OrderNumber'] ?? null;
    }

    /** @return string Payment ID (TID) */
    public function getPaymentId()
    {
        return $this->data['PaymentId'] ?? null;
    }

    /** @return string Credit, Debit, etc */
    public function getPaymentMethod()
    {
        return $this->data['PaymentMethod'] ?? null;
    }

    /** @return int Amount in cents */
    public function getAmount()
    {
        $amount = $this->data['Amount'] ?? null;
        return $amount === null ? null : (int) $amount;
    }

    /** @return string Return code from issuer (e.g., "0" for success) */
    public function getStatusCode()
    {
        return $this->data['StatusCode'] ?? null;
    }

    /** @return string Human-readable status (e.g., "Captura - Sucesso") */
    public function getStatusDescription()
    {
        return $this->data['StatusDescription'] ?? null;
    }

    /** @return string|null Recurring subscription ID (recurring transactions only) */
    public function getSubscriptionId()
    {
        return $this->data['SubscriptionId'] ?? null;
    }

    /** @return string|null Billing ID (recurring transactions only) */
    public function getBillingId()
    {
        return $this->data['BillingId'] ?? null;
    }

    /** @return array Raw payload */
    public function getRawData()
    {
        return $this->data;
    }
}
