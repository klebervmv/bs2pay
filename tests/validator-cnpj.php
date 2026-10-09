<?php

/**
 * Teste unitário (sem rede) da validação de CPF/CNPJ.
 * Uso: php tests/validator-cnpj.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Adiq\Utils\Validator;

$cases = [
    // [descrição, valor, esperado]
    ['CNPJ numérico sem máscara',            '11222333000181',     true],
    ['CNPJ numérico com máscara',            '11.222.333/0001-81', true],
    ['CNPJ numérico DV errado',              '11222333000182',     false],
    ['CNPJ alfanumérico com máscara',        '12.ABC.345/01DE-35', true],
    ['CNPJ alfanumérico sem máscara',        '12ABC34501DE35',     true],
    ['CNPJ alfanumérico minúsculo',          '12abc34501de35',     true],
    ['CNPJ alfanumérico DV errado',          '12ABC34501DE36',     false],
    ['CNPJ alfanumérico letra alterada',     '12ABD34501DE35',     false],
    ['DV não pode ser letra',                '12ABC34501DEAB',     false],
    ['CNPJ sequência repetida (dígitos)',    '00000000000000',     false],
    ['CNPJ sequência repetida (letras)',     'AAAAAAAAAAAAAA',     false],
    ['CNPJ curto',                           '12ABC34501DE3',      false],
    ['CNPJ longo',                           '12ABC34501DE355',    false],
    ['CNPJ vazio',                           '',                   false],
];

$fail = 0;
foreach ($cases as $c) {
    list($desc, $val, $expected) = $c;
    $got = Validator::validateCNPJ($val);
    $ok = $got === $expected;
    if (!$ok) {
        $fail++;
    }
    printf("[%s] %s (%s)\n", $ok ? 'OK' : 'FALHOU', $desc, $val);
}

// CPF não deve ser afetado
foreach ([['CPF válido', '51115672088', true], ['CPF inválido', '51115672089', false]] as $c) {
    $ok = Validator::validateCPF($c[1]) === $c[2];
    if (!$ok) {
        $fail++;
    }
    printf("[%s] %s (%s)\n", $ok ? 'OK' : 'FALHOU', $c[0], $c[1]);
}

echo $fail === 0 ? "\nTodos os testes passaram.\n" : "\n{$fail} teste(s) falharam.\n";
exit($fail === 0 ? 0 : 1);
