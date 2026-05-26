<?php

namespace Adiq\Dto;

/**
 * Estrutura auxiliar para montar o bloco "customer" da request de pagamento.
 *
 * Não é uma resposta — é um value object com toArray() para serializar.
 */
class CustomerDto
{
    /** @var array<string,mixed> */
    private $data = [];

    /** @param array<string,mixed> $data */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /** @param string $value @return self */
    public function setFirstName($value) { $this->data['firstName'] = $value; return $this; }

    /** @param string $value @return self */
    public function setLastName($value) { $this->data['lastName'] = $value; return $this; }

    /** @param string $value @return self */
    public function setEmail($value) { $this->data['email'] = $value; return $this; }

    /** @param string $type cpf|cnpj @param string $number @return self */
    public function setDocument($type, $number)
    {
        $this->data['documentType'] = $type;
        $this->data['documentNumber'] = $number;
        return $this;
    }

    /** @param string $value @return self */
    public function setPhoneNumber($value) { $this->data['phoneNumber'] = $value; return $this; }

    /** @param string $value @return self */
    public function setMobilePhoneNumber($value) { $this->data['mobilePhoneNumber'] = $value; return $this; }

    /** @return self */
    public function setAddress($street, $number, $city, $state, $zipCode, $country = 'BR', $complement = null)
    {
        $this->data['address'] = $street;
        $this->data['addressNumber'] = $number;
        $this->data['city'] = $city;
        $this->data['state'] = $state;
        $this->data['zipCode'] = $zipCode;
        $this->data['country'] = $country;
        if ($complement !== null) {
            $this->data['addressComplement'] = $complement;
        }
        return $this;
    }

    /** @param string $value @return self */
    public function setIpAddress($value) { $this->data['ipAddress'] = $value; return $this; }

    /** @param string $key @param mixed $value @return self */
    public function set($key, $value) { $this->data[$key] = $value; return $this; }

    /** @return array<string,mixed> */
    public function toArray() { return $this->data; }
}
