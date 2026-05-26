<?php

namespace Adiq\Dto;

/**
 * Resposta de consulta BIN.
 *
 * GET /v1/bins/search/{binNumber} retorna um ARRAY:
 * [
 *   {
 *     "bin": "12345678",
 *     "bank": "Emissor",
 *     "brand": "MASTER",
 *     "country": "BRL",
 *     "cardType": "Crédito"
 *   }
 * ]
 *
 * Este DTO retorna a primeira entrada (ou especificada por index).
 */
class BinResponseDto extends ResponseDto
{
    /**
     * Retorna a primeira entrada BIN (mais comum ter apenas uma).
     *
     * @param int $index
     * @return array|null
     */
    public function getEntry($index = 0)
    {
        $entries = $this->body;
        if (!is_array($entries) || !isset($entries[$index])) {
            return null;
        }
        return $entries[$index];
    }

    /** @return string|null */
    public function getBin($index = 0)
    {
        $entry = $this->getEntry($index);
        return $entry['bin'] ?? null;
    }

    /** @return string|null Bank name */
    public function getBank($index = 0)
    {
        $entry = $this->getEntry($index);
        return $entry['bank'] ?? null;
    }

    /** @return string|null Visa, Mastercard, Elo, Amex, etc */
    public function getBrand($index = 0)
    {
        $entry = $this->getEntry($index);
        return $entry['brand'] ?? null;
    }

    /** @return string|null Country code (BRL, USD, etc) */
    public function getCountry($index = 0)
    {
        $entry = $this->getEntry($index);
        return $entry['country'] ?? null;
    }

    /** @return string|null Crédito, Débito */
    public function getCardType($index = 0)
    {
        $entry = $this->getEntry($index);
        return $entry['cardType'] ?? null;
    }

    /** @return int Total number of entries */
    public function getEntryCount()
    {
        return is_array($this->body) ? count($this->body) : 0;
    }
}
