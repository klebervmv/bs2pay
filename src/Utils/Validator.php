<?php

namespace Adiq\Utils;

use Adiq\Exceptions\ValidationException;

/**
 * Validações de entrada (PAN, CPF, CNPJ, email, data de expiração, IP, valor).
 */
class Validator
{
    /**
     * Valida PAN (13-19 dígitos) com algoritmo de Luhn.
     *
     * @param string $cardNumber
     * @return bool
     */
    public static function validateCardNumber($cardNumber)
    {
        $pan = Helper::onlyDigits($cardNumber);
        $len = strlen($pan);
        if ($len < 13 || $len > 19) {
            return false;
        }
        return self::luhn($pan);
    }

    /**
     * Algoritmo de Luhn.
     *
     * @param string $number Somente dígitos
     * @return bool
     */
    public static function luhn($number)
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }
        return ($sum % 10) === 0;
    }

    /**
     * Valida CPF.
     *
     * @param string $cpf
     * @return bool
     */
    public static function validateCPF($cpf)
    {
        $cpf = Helper::onlyDigits($cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ($cpf[$t] != $digit) {
                return false;
            }
        }
        return true;
    }

    /**
     * Valida CNPJ.
     *
     * @param string $cnpj
     * @return bool
     */
    public static function validateCNPJ($cnpj)
    {
        $cnpj = Helper::onlyDigits($cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        $weights1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        $weights2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
        foreach ([12 => $weights1, 13 => $weights2] as $pos => $w) {
            $sum = 0;
            for ($i = 0; $i < count($w); $i++) {
                $sum += $cnpj[$i] * $w[$i];
            }
            $mod = $sum % 11;
            $digit = $mod < 2 ? 0 : 11 - $mod;
            if ($cnpj[$pos] != $digit) {
                return false;
            }
        }
        return true;
    }

    /**
     * Valida email.
     *
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email)
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Valida data de expiração (MM, YY ou YYYY).
     *
     * @param string|int $month
     * @param string|int $year
     * @return bool
     */
    public static function validateExpirationDate($month, $year)
    {
        $month = (int) $month;
        $year = (int) $year;
        if ($month < 1 || $month > 12) {
            return false;
        }
        if ($year < 100) {
            $year += 2000;
        }
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');
        if ($year < $currentYear) {
            return false;
        }
        if ($year === $currentYear && $month < $currentMonth) {
            return false;
        }
        if ($year > $currentYear + 20) {
            return false;
        }
        return true;
    }

    /**
     * Valida IP (v4 ou v6).
     *
     * @param string $ip
     * @return bool
     */
    public static function validateIpAddress($ip)
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP);
    }

    /**
     * Valida valor em centavos (inteiro positivo).
     *
     * @param int $amount
     * @return bool
     */
    public static function validateAmount(int $amount)
    {
        if (!is_numeric($amount)) {
            return false;
        }
        $intAmount = (int) $amount;
        return $intAmount > 0 && (string) $intAmount === (string) $amount;
    }

    /**
     * Garante campos obrigatórios em um array. Lança ValidationException se faltar algum.
     *
     * @param array $data
     * @param array $required Lista de paths (dot-notation)
     * @throws ValidationException
     */
    public static function assertRequired(array $data, array $required)
    {
        $missing = [];
        foreach ($required as $field) {
            $value = Helper::get($data, $field);
            if ($value === null || $value === '') {
                $missing[$field] = "Campo obrigatório: {$field}";
            }
        }
        if (!empty($missing)) {
            throw new ValidationException('Campos obrigatórios ausentes.', $missing);
        }
    }

    /**
     * Valida ambiente.
     *
     * @param string $env
     * @return bool
     */
    public static function validateEnvironment($env)
    {
        return in_array($env, ['sandbox', 'homologation', 'production'], true);
    }
}
