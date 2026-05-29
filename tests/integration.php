<?php

require __DIR__ . '/../vendor/autoload.php';

use Adiq\AdiqPaymentSDK;

// Carregar variáveis de ambiente (.env na raiz do SDK)
$dotenv = __DIR__ . '/../.env';
if (file_exists($dotenv)) {
    $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

function uuid4() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// code3DS gerado pelo SDK frontend da Adiq tem 32 chars hex sem hifens.
// Exemplo real: de13945d654146028f91e1a9062693ac
function code3ds() {
    return bin2hex(random_bytes(16));
}

// Dados do cliente usados em todos os testes de pagamento (obrigatórios para antifraude)
$customer = [
    'documentType'        => 'cpf',
    'documentNumber'      => '51115672088',
    'firstName'           => 'Jose',
    'lastName'            => 'Silva',
    'email'               => 'jose.silva@email.com',
    'phoneNumber'         => '1122542454',
    'mobilePhoneNumber'   => '11987683332',
    'address'             => 'Rua Luiz Vieira',
    'addressNumber'       => '134',
    'complement'          => 'apto. 22',
    'city'                => 'Sao Paulo',
    'state'               => 'SP',
    'zipCode'             => '09876098',
    'ipAddress'           => '192.168.1.100',
    'country'             => 'BR',
];

try {
    $sdk = new AdiqPaymentSDK(
        getenv('ADIQ_CLIENT_ID'),
        getenv('ADIQ_CLIENT_SECRET'),
        "homologation"
    );

    echo "✅ SDK inicializado com sucesso!\n";
    echo "   Cliente ID: ".getenv('ADIQ_CLIENT_ID');
    echo "   Ambiente: homologation\n\n";

    $numberToken  = null;
    $paymentId    = null;
    $preAuthPayId = null;

    // ----------------------------------------------------------------
    // Teste 1: Tokenizar cartão Visa de teste
    // ----------------------------------------------------------------
    echo "🧪 Teste 1: Tokenizar Cartão\n";
    try {
        $tokenResponse = $sdk->tokens->create([
            'cardNumber' => '4111111111111111'
        ]);

        if ($tokenResponse->isSuccess()) {
            $numberToken = $tokenResponse->getNumberToken();
            echo "   ✅ Token criado: " . substr($numberToken, 0, 20) . "...\n\n";
        } else {
            echo "   ❌ Erro: " . $tokenResponse->getErrorDescription() . "\n\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
    }

    // ----------------------------------------------------------------
    // Teste 2: Consultar BIN
    // ----------------------------------------------------------------
    echo "🧪 Teste 2: Consultar BIN\n";
    try {
        $binResponse = $sdk->bin->search('222763');

        if ($binResponse->isSuccess()) {
            $bins = $binResponse->getBody();
            echo "   ✅ BIN encontrado!\n";
            foreach ($bins as $b) {
                echo "      banco: {$b['bank']}  |  bandeira: {$b['brand']}  |  tipo: {$b['cardType']}\n";
            }
            echo "\n";
        } else {
            echo "   ❌ Erro: " . $binResponse->getErrorDescription() . "\n\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
    }

    // ----------------------------------------------------------------
    // Teste 3: Zero Auth — valida cartão sem cobrança
    // Conforme doc: cardholderName, securityCode, numberToken,
    // expirationMonth, expirationYear e transactionType são obrigatórios.
    // O sandbox também exige cardNumber no body.
    // ----------------------------------------------------------------
    echo "🧪 Teste 3: Zero Auth (validação de cartão sem cobrança)\n";
    if ($numberToken) {
        try {
            $zeroAuthResponse = $sdk->zeroAuth->verify([
                'numberToken'     => $numberToken,
                'cardNumber'      => '4111111111111111',
                'cardholderName'  => 'JOSE SILVA',
                'securityCode'    => '123',
                'expirationMonth' => '12',
                'expirationYear'  => '28',
                'transactionType' => 'credit',
            ]);

            if ($zeroAuthResponse->isSuccess()) {
                echo "   ✅ Cartão válido! cardAuthSuccess: " .
                    ($zeroAuthResponse->isCardValid() ? 'true' : 'false') . "\n\n";
            } else {
                echo "   ❌ Erro: " . $zeroAuthResponse->getErrorDescription() . "\n\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
        }
    } else {
        echo "   ⚠️  Ignorado — token não disponível (Teste 1 falhou)\n\n";
    }

    // ----------------------------------------------------------------
    // Teste 4: Autorizar e capturar pagamento (captureType = ac)
    // amount = 1000 centavos → sandbox retorna aprovado conforme doc
    // ----------------------------------------------------------------
    echo "🧪 Teste 4: Autorizar e Capturar Pagamento (ac)\n";
    if ($numberToken) {
        try {
            $orderNumber = 'T' . date('ymdHis'); // max 13 chars

            $paymentResponse = $sdk->payments->create([
                'payment' => [
                    'transactionType' => 'credit',
                    'amount'          => 1000,
                    'currencyCode'    => 'brl',
                    'productType'     => 'avista',
                    'installments'    => 1,
                    'captureType'     => 'ac',
                    'recurrent'       => false,
                ],
                'cardInfo' => [
                    'numberToken'     => $numberToken,
                    'cardholderName'  => 'JOSE SILVA',
                    'securityCode'    => '123',
                    'brand'           => 'visa',
                    'expirationMonth' => '12',
                    'expirationYear'  => '28',
                ],
                'customer'   => $customer,
                'sellerInfo' => [
                    'orderNumber'    => $orderNumber,
                    'softDescriptor' => 'PARCUSA*TEST',
                    'codeAntiFraud'  => uuid4(),
                ],
            ]);

            if ($paymentResponse->isSuccess()) {
                $paymentId = $paymentResponse->getPaymentId();
                echo "   ✅ Pagamento aprovado!\n";
                echo "      paymentId:   " . $paymentId . "\n";
                echo "      returnCode:  " . $paymentResponse->getReturnCode() . "\n";
                echo "      orderNumber: " . $orderNumber . "\n\n";
            } else {
                echo "   ❌ Recusado: " . $paymentResponse->getErrorDescription() . "\n";
                echo "      returnCode: " . $paymentResponse->getReturnCode() . "\n\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
        }
    } else {
        echo "   ⚠️  Ignorado — token não disponível (Teste 1 falhou)\n\n";
    }

    // ----------------------------------------------------------------
    // Teste 5: Pré-autorizar (pa) + captura tardia
    // amount = 2000 → sandbox retorna aprovado
    // ----------------------------------------------------------------
    echo "🧪 Teste 5: Pré-autorizar (pa) + Captura Tardia\n";
    if ($numberToken) {
        try {
            // Cria novo token — o token anterior foi consumido pelo Teste 4
            $tokenResp2 = $sdk->tokens->create(['cardNumber' => '4111111111111111']);
            $token2 = $tokenResp2->isSuccess() ? $tokenResp2->getNumberToken() : $numberToken;

            $orderNumber2 = 'P' . date('ymdHis'); // max 13 chars

            $preAuthResponse = $sdk->payments->create([
                'payment' => [
                    'transactionType' => 'credit',
                    'amount'          => 2000,
                    'currencyCode'    => 'brl',
                    'productType'     => 'avista',
                    'installments'    => 1,
                    'captureType'     => 'pa',
                    'recurrent'       => false,
                ],
                'cardInfo' => [
                    'numberToken'     => $token2,
                    'cardholderName'  => 'JOSE SILVA',
                    'securityCode'    => '123',
                    'brand'           => 'visa',
                    'expirationMonth' => '12',
                    'expirationYear'  => '28',
                ],
                'customer'   => $customer,
                'sellerInfo' => [
                    'orderNumber'    => $orderNumber2,
                    'softDescriptor' => 'PARCUSA*PA',
                    'codeAntiFraud'  => uuid4(),
                ],
            ]);

            if ($preAuthResponse->isSuccess()) {
                $preAuthPayId = $preAuthResponse->getPaymentId();
                echo "   ✅ Pré-autorização aprovada! paymentId: " . $preAuthPayId . "\n";

                $captureResponse = $sdk->payments->capture($preAuthPayId, 2000);

                if ($captureResponse->isSuccess()) {
                    echo "   ✅ Captura tardia realizada com sucesso!\n\n";
                } else {
                    echo "   ❌ Erro na captura: " . $captureResponse->getErrorDescription() . "\n\n";
                }
            } else {
                echo "   ❌ Pré-autorização recusada: " . $preAuthResponse->getErrorDescription() . "\n\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
        }
    } else {
        echo "   ⚠️  Ignorado — token não disponível (Teste 1 falhou)\n\n";
    }

    // ----------------------------------------------------------------
    // Teste 6: Consultar pagamento por ID (v2 retorna mais dados)
    // ----------------------------------------------------------------
    echo "🧪 Teste 6: Consultar Pagamento por ID\n";
    $queryId = $paymentId ?? $preAuthPayId;
    if ($queryId) {
        try {
            $getResponse = $sdk->payments->get($queryId, 'v2');

            if ($getResponse->isSuccess()) {
                echo "   ✅ Consulta realizada!\n";
                echo "      paymentId:  " . $getResponse->getPaymentId() . "\n";
                echo "      status:     " . $getResponse->get('paymentAuthorization.statusDescription') . "\n\n";
            } else {
                echo "   ❌ Erro: " . $getResponse->getErrorDescription() . "\n\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
        }
    } else {
        echo "   ⚠️  Ignorado — nenhum paymentId disponível (Testes 4/5 falharam)\n\n";
    }

    // ----------------------------------------------------------------
    // Teste 7: Cancelar/estornar pagamento do Teste 4
    // ----------------------------------------------------------------
    echo "🧪 Teste 7: Cancelar/Estornar Pagamento\n";
    if ($paymentId) {
        try {
            $cancelResponse = $sdk->payments->cancel($paymentId, 1000);

            if ($cancelResponse->isSuccess()) {
                echo "   ✅ Pagamento cancelado com sucesso!\n";
                echo "      paymentId: " . $cancelResponse->getPaymentId() . "\n\n";
            } else {
                echo "   ❌ Erro no cancelamento: " . $cancelResponse->getErrorDescription() . "\n\n";
            }
        } catch (\Exception $e) {
            echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
        }
    } else {
        echo "   ⚠️  Ignorado — nenhum paymentId disponível (Teste 4 falhou)\n\n";
    }

    // ================================================================
    // TESTES 3DS 2.0 — Validação dos cenários documentados
    // ================================================================
    // Doc: https://developers.adiq.io/manual/ecommerce (3DS 2.0)
    //
    // Limitação: em produção o `code3DS` é gerado pelo SDK JS/Mobile
    // da Adiq após device data collection no browser/app do cliente.
    // Aqui geramos um UUID válido para exercitar a aceitação do payload
    // e validar a forma da resposta.
    // ================================================================

    echo "================================================================\n";
    echo "  3DS 2.0 — Validação de cenários\n";
    echo "================================================================\n\n";

    // Helper: monta payload completo com 3DS + DeviceInfo
    $build3dsRequest = function ($numberToken, $brand, $amount, $code3ds) use ($customer) {
        return [
            'payment' => [
                'transactionType' => 'credit',
                'amount'          => $amount,
                'currencyCode'    => 'brl',
                'productType'     => 'avista',
                'installments'    => 1,
                'captureType'     => 'ac',
                'recurrent'       => false,
            ],
            'cardInfo' => [
                'numberToken'     => $numberToken,
                'cardholderName'  => 'JOSE SILVA',
                'securityCode'    => '123',
                'brand'           => $brand,
                'expirationMonth' => '12',
                'expirationYear'  => '28',
            ],
            'customer'   => $customer,
            'sellerInfo' => [
                'orderNumber'     => 'D' . date('ymdHis'),
                'softDescriptor'  => 'PARCUSA*3DS',
                'codeAntiFraud'   => uuid4(),
                'code3DS'         => $code3ds,
                'urlSite3DS'      => 'parceladopay.com',
                'threeDsDataOnly' => false,
            ],
            'DeviceInfo' => [
                'HttpAcceptBrowserValue'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'HttpAcceptContent'            => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'HttpBrowserLanguage'          => 'pt-BR',
                'HttpBrowserJavaEnabled'       => 'N',
                'HttpBrowserJavaScriptEnabled' => 'Y',
                'HttpBrowserColorDepth'        => '24',
                'HttpBrowserScreenHeight'      => '937',
                'HttpBrowserScreenWidth'       => '1920',
                'HttpBrowserTimeDifference'    => '180',
                'UserAgentBrowserValue'        => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ],
        ];
    };

    // Helper: compara esperado x obtido e imprime resultado
    $assertEq = function ($label, $expected, $actual) {
        $ok = ($expected === $actual);
        $mark = $ok ? '✅' : '❌';
        $exp = is_bool($expected) ? var_export($expected, true) : ('"' . $expected . '"');
        $got = is_bool($actual)   ? var_export($actual, true)   : (is_null($actual) ? 'NULL' : '"' . $actual . '"');
        echo "      {$mark} {$label}  esperado={$exp}  obtido={$got}\n";
        return $ok;
    };

    // Helper: confirma que campo veio preenchido
    $assertNotEmpty = function ($label, $value) {
        $ok = !empty($value);
        $mark = $ok ? '✅' : '❌';
        $preview = is_string($value) && strlen($value) > 50 ? substr($value, 0, 50) . '...' : ($value ?: 'AUSENTE');
        echo "      {$mark} {$label} presente: {$preview}\n";
        return $ok;
    };

    // ----------------------------------------------------------------
    // Teste 8: 3DS SILENT — cartão sem challenge, autenticação OK
    // Card: 4000000000002701 (VISA) | Esperado: status=Silent, eci=05
    // ----------------------------------------------------------------
    echo "🧪 Teste 8: 3DS Silent (Frictionless) — VISA aprovado sem challenge\n";
    echo "   Cartão: 4000000000002701 | Esperado: status=Silent, eci=05\n";
    try {
        $tokenResp = $sdk->tokens->create(['cardNumber' => '4000000000002701']);
        if (!$tokenResp->isSuccess()) {
            echo "   ❌ Tokenização falhou: " . $tokenResp->getErrorDescription() . "\n\n";
        } else {
            $code3ds = code3ds();
            $request = $build3dsRequest($tokenResp->getNumberToken(), 'visa', 1000, $code3ds);
            $resp = $sdk->payments->create($request);

            echo "   Validações da resposta:\n";
            $assertEq('threeDs.status                  ', 'Silent', $resp->get('threeDs.status'));
            $assertEq('threeDs.eci                     ', '05',     $resp->get('threeDs.eci'));
            $assertEq('threeDs.threeDsStatus           ', 'AUTHENTICATION_SUCCESSFUL', $resp->get('threeDs.threeDsStatus'));
            $assertEq('threeDs.paresStatus             ', 'Y',      $resp->get('threeDs.paresStatus'));
            $assertNotEmpty('threeDs.threeDsVersion          ', $resp->get('threeDs.threeDsVersion'));
            $assertEq('paymentAuthorization.returnCode ', '0', $resp->get('paymentAuthorization.returnCode'));
            $assertEq('isChallenge()                   ', false, $resp->isChallenge());
            $assertEq('isApproved()                    ', true,  $resp->isApproved());
            echo "      ℹ️  paymentId: " . ($resp->getPaymentId() ?: 'N/D') . "\n\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
    }

    // ----------------------------------------------------------------
    // Teste 9: 3DS CHALLENGE — cartão exige desafio
    // Card: 4000000000002503 (VISA) | Esperado: status=Challenge + acsUrl/pareq/txId
    // ----------------------------------------------------------------
    echo "🧪 Teste 9: 3DS Challenge — VISA exigindo desafio\n";
    echo "   Cartão: 4000000000002503 | Esperado: status=Challenge + acsUrl/pareq/txId\n";
    try {
        $tokenResp = $sdk->tokens->create(['cardNumber' => '4000000000002503']);
        if (!$tokenResp->isSuccess()) {
            echo "   ❌ Tokenização falhou: " . $tokenResp->getErrorDescription() . "\n\n";
        } else {
            $code3ds = code3ds();
            $request = $build3dsRequest($tokenResp->getNumberToken(), 'visa', 1000, $code3ds);
            $resp = $sdk->payments->create($request);

            echo "   Validações da resposta:\n";
            $assertEq('threeDs.status                       ', 'Challenge', $resp->get('threeDs.status'));
            $assertEq('isChallenge()                        ', true, $resp->isChallenge());
            $assertNotEmpty('threeDs.acsUrl                       ', $resp->get('threeDs.acsUrl'));
            $assertNotEmpty('threeDs.pareq                        ', $resp->get('threeDs.pareq'));
            $assertNotEmpty('threeDs.authenticationTransactionId  ', $resp->get('threeDs.authenticationTransactionId'));
            $assertNotEmpty('threeDs.threeDsVersion               ', $resp->get('threeDs.threeDsVersion'));

            echo "\n   ⚠️  Próximo passo (manual / front-end):\n";
            echo "      1. JS: Adiq3ds.InitChallenge(acsUrl, pareq, authenticationTransactionId)\n";
            echo "      2. Cliente responde challenge → recebe validateToken\n";
            echo "      3. Backend: POST /v1/payments/validate { code3ds, validateToken }\n\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
    }

    // ----------------------------------------------------------------
    // Teste 10: 3DS FAILED — rejeição pelo emissor sem challenge
    // Card: 4000000000002925 (VISA) | Esperado: eci=07 (não autenticado)
    // ----------------------------------------------------------------
    echo "🧪 Teste 10: 3DS Auth Failed — VISA rejeitado pelo emissor sem challenge\n";
    echo "   Cartão: 4000000000002925 | Esperado: eci=07, sem challenge, não aprovado por 3DS\n";
    try {
        $tokenResp = $sdk->tokens->create(['cardNumber' => '4000000000002925']);
        if (!$tokenResp->isSuccess()) {
            echo "   ❌ Tokenização falhou: " . $tokenResp->getErrorDescription() . "\n\n";
        } else {
            $code3ds = code3ds();
            $request = $build3dsRequest($tokenResp->getNumberToken(), 'visa', 1000, $code3ds);
            $resp = $sdk->payments->create($request);

            echo "   Validações da resposta:\n";
            $assertEq('threeDs.eci             ', '07',   $resp->get('threeDs.eci'));
            $assertEq('threeDs.status          ', 'Fail', $resp->get('threeDs.status'));
            $assertEq('threeDs.threeDsStatus   ', 'AUTHENTICATION_FAILED', $resp->get('threeDs.threeDsStatus'));
            $assertEq('isChallenge()           ', false, $resp->isChallenge());

            // ATENÇÃO: sem Credit Stop ativo na conta, a Adiq AUTORIZA a transação
            // mesmo com 3DS reprovado (eci=07). Quem decide aceitar/rejeitar
            // é o lojista olhando threeDs.eci/threeDs.status.
            $assertEq('isApproved()            ', true,  $resp->isApproved());

            echo "      ⚠️  ATENÇÃO: pagamento aprovado mesmo com 3DS falhando (eci=07).\n";
            echo "          Significa que 'Credit Stop' não está ativo nesta conta.\n";
            echo "          O lojista é responsável por rejeitar com base em threeDs.eci.\n";
            echo "      ℹ️  description: " . ($resp->getDescription() ?: 'N/D') . "\n";
            echo "      ℹ️  paymentId:   " . ($resp->getPaymentId()   ?: 'N/D') . "\n";
            echo "      ℹ️  authCode:    " . ($resp->getAuthorizationCode() ?: 'N/D') . "\n";

            // CONFIRMACAO: consulta o paymentId para ver se a venda foi mesmo processada
            $payId10 = $resp->getPaymentId();
            if ($payId10) {
                echo "\n   🔍 Consultando paymentId para confirmar processamento real...\n";
                $check = $sdk->payments->get($payId10, 'v2');
                if ($check->isSuccess()) {
                    echo "      ℹ️  statusDescription: " . ($check->get('paymentAuthorization.statusDescription') ?: 'N/D') . "\n";
                    echo "      ℹ️  amount autorizado:  " . ($check->get('paymentAuthorization.amount') ?: 'N/D') . " centavos\n";
                    echo "      ℹ️  releaseAt:         " . ($check->get('paymentAuthorization.releaseAt') ?: 'N/D') . "\n";
                    echo "      ➡️  Se 'statusDescription' for Capturada/Autorizada, a venda FOI processada.\n";
                } else {
                    echo "      ℹ️  Consulta falhou: " . $check->getErrorDescription() . "\n";
                }
            }
            echo "\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
    }

    // ----------------------------------------------------------------
    // Teste 11: Sem code3DS — reproduz o cenário "passou direto"
    // Confirma o comportamento documentado: sem code3DS, 3DS não roda.
    // ----------------------------------------------------------------
    echo "🧪 Teste 11: Sem code3DS — confirma comportamento documentado\n";
    echo "   Doc: \"If code3DS is not submitted, the 3DS will not run.\"\n";
    try {
        $tokenResp = $sdk->tokens->create(['cardNumber' => '4000000000002701']);
        if (!$tokenResp->isSuccess()) {
            echo "   ❌ Tokenização falhou: " . $tokenResp->getErrorDescription() . "\n\n";
        } else {
            // payload sem code3DS nem DeviceInfo
            $request = [
                'payment' => [
                    'transactionType' => 'credit',
                    'amount'          => 1000,
                    'currencyCode'    => 'brl',
                    'productType'     => 'avista',
                    'installments'    => 1,
                    'captureType'     => 'ac',
                    'recurrent'       => false,
                ],
                'cardInfo' => [
                    'numberToken'     => $tokenResp->getNumberToken(),
                    'cardholderName'  => 'JOSE SILVA',
                    'securityCode'    => '123',
                    'brand'           => 'visa',
                    'expirationMonth' => '12',
                    'expirationYear'  => '28',
                ],
                'customer'   => $customer,
                'sellerInfo' => [
                    'orderNumber'    => 'N' . date('ymdHis'),
                    'softDescriptor' => 'PARCUSA*NO3DS',
                    'codeAntiFraud'  => uuid4(),
                ],
            ];
            $resp = $sdk->payments->create($request);

            echo "   Validações da resposta:\n";
            $threeDs = $resp->get('threeDs');
            $hasBlock = !empty($threeDs);
            $mark = $hasBlock ? '⚠️ ' : '✅';
            echo "      {$mark} Bloco threeDs ausente: " . ($hasBlock ? 'PRESENTE (inesperado)' : 'AUSENTE (esperado)') . "\n";
            $assertEq('isApproved()           ', true, $resp->isApproved());
            echo "      ℹ️  Conclusão: sem code3DS, a Adiq aprova SEM rodar 3DS.\n";
            echo "      ℹ️  Esse era o cenário do seu teste \"passou direto\".\n\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
    }

    // ----------------------------------------------------------------
    // Teste 12: 3DS Challenge FAIL durante processamento — VISA
    // Card: 4000000000002644 | Esperado: 3DS falha → cai para Anti-Fraud (ClearSale)
    // Regra de negócio: 3DS aprovado → aprova direto / 3DS reprovado → AntiFraud decide
    // ----------------------------------------------------------------
    echo "🧪 Teste 12: 3DS Falha durante challenge → fallback para Anti-Fraud (ClearSale)\n";
    echo "   Cartão: 4000000000002644 | Esperado: 3DS falha → Anti-Fraud decide\n";
    try {
        $tokenResp = $sdk->tokens->create(['cardNumber' => '4000000000002644']);
        if (!$tokenResp->isSuccess()) {
            echo "   ❌ Tokenização falhou: " . $tokenResp->getErrorDescription() . "\n\n";
        } else {
            $code3ds = code3ds();
            $request = $build3dsRequest($tokenResp->getNumberToken(), 'visa', 1000, $code3ds);
            $resp = $sdk->payments->create($request);

            echo "   Resposta completa da Adiq:\n";
            echo "      threeDs.status:           " . ($resp->get('threeDs.status')        ?: 'N/D') . "\n";
            echo "      threeDs.eci:              " . ($resp->get('threeDs.eci')           ?: 'N/D') . "\n";
            echo "      threeDs.threeDsStatus:    " . ($resp->get('threeDs.threeDsStatus') ?: 'N/D') . "\n";
            echo "      isChallenge():            " . var_export($resp->isChallenge(), true) . "\n";
            echo "      isApproved():             " . var_export($resp->isApproved(),  true) . "\n";
            echo "      returnCode:               " . ($resp->getReturnCode()              ?: 'N/D') . "\n";
            echo "      description:              " . ($resp->getDescription()             ?: 'N/D') . "\n";
            echo "      paymentId:                " . ($resp->getPaymentId()               ?: 'N/D') . "\n";
            echo "      authCode:                 " . ($resp->getAuthorizationCode()       ?: 'N/D') . "\n";

            // Inspeciona se a Adiq retornou bloco antiFraud na resposta
            $af = $resp->get('antiFraud') ?: $resp->get('AntiFraud');
            if (!empty($af)) {
                echo "   🛡️  Bloco antiFraud presente na resposta:\n";
                if (is_array($af)) {
                    foreach ($af as $k => $v) {
                        echo "      antiFraud.{$k}: " . (is_scalar($v) ? $v : json_encode($v)) . "\n";
                    }
                }
            } else {
                echo "   ℹ️  Bloco antiFraud NÃO veio na resposta — pode não estar habilitado na conta\n";
            }

            // Consulta o paymentId para confirmar status final
            $payId12 = $resp->getPaymentId();
            if ($payId12) {
                echo "\n   🔍 Consultando paymentId para confirmar status final...\n";
                $check = $sdk->payments->get($payId12, 'v2');
                if ($check->isSuccess()) {
                    echo "      statusDescription: " . ($check->get('paymentAuthorization.statusDescription') ?: 'N/D') . "\n";
                    echo "      returnCode:        " . ($check->get('paymentAuthorization.returnCode')        ?: 'N/D') . "\n";
                    echo "      releaseAt:         " . ($check->get('paymentAuthorization.releaseAt')         ?: 'N/D') . "\n";

                    $statusFinal = $check->get('paymentAuthorization.statusDescription');
                    if (in_array($statusFinal, ['Capturada', 'Autorizada'], true)) {
                        echo "      ➡️  Venda PROCESSADA (AntiFraud aprovou ou não atuou).\n";
                    } else {
                        echo "      ➡️  Venda NÃO processada — status: {$statusFinal}\n";
                    }
                } else {
                    echo "      ℹ️  Consulta falhou: " . $check->getErrorDescription() . "\n";
                }
            } else {
                echo "\n   ℹ️  Sem paymentId → venda foi rejeitada antes da autorização\n";
                echo "      (provavelmente AntiFraud negou ou 3DS bloqueou via Credit Stop)\n";
            }
            echo "\n";
        }
    } catch (\Exception $e) {
        echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
    }

    // ================================================================
    // Teste 13: 3DS CHALLENGE + VALIDATE — Simulação do fluxo completo
    // ================================================================
    echo "================================================================\n";
    echo "  3DS Challenge + Validate — Fluxo completo\n";
    echo "================================================================\n\n";

    echo "🧪 Teste 13: 3DS Challenge com Validate\n";
    echo "   Cartão: 4000000000002503 (Challenge) → Simula validate com token fictício\n";
    try {
        $tokenResp = $sdk->tokens->create(['cardNumber' => '4000000000002503']);
        if (!$tokenResp->isSuccess()) {
            echo "   ❌ Tokenização falhou: " . $tokenResp->getErrorDescription() . "\n\n";
        } else {
            $code3dsForChallenge = code3ds();
            $request = $build3dsRequest($tokenResp->getNumberToken(), 'visa', 1000, $code3dsForChallenge);
            $resp = $sdk->payments->create($request);

            echo "   Passo 1: Criar pagamento com 3DS Challenge\n";
            $isChallenge = $resp->isChallenge();
            $mark = $isChallenge ? '✅' : '❌';
            echo "      {$mark} isChallenge(): " . var_export($isChallenge, true) . "\n";

            if ($isChallenge) {
                echo "      ℹ️  Code3DS usado: " . $code3dsForChallenge . "\n";
                echo "      ℹ️  acsUrl presente: " . (!empty($resp->get('threeDs.acsUrl')) ? 'SIM' : 'NÃO') . "\n";

                // Simula resposta do cliente após desafio
                // Em homologação, precisamos ver qual validateToken a ADIQ espera
                // Por enquanto, vamos usar um token fictício válido
                echo "\n   Passo 2: Simular resposta do cliente (validateToken)\n";
                $validateToken = 'eJytUMtuwjAM/BXLT8ANE2VtN02TLlM17Ira7cJ1SHFxw' .
                                'KTL7uNHktL2fud7t8s94QWJHJHJsWJEv3DIEh+N-B5YuV1M8mJYeOxMSKvPm' .
                                'Ey_gEO94IKjVPfGKJO4SHDmKWBP_9KkiBWpH0gHzPwCQFWmS0';

                echo "      ℹ️  validateToken (fictício): " . substr($validateToken, 0, 40) . "...\n";

                echo "\n   Passo 3: Chamar /payments/validate\n";
                try {
                    $validateResp = $sdk->payments->validate([
                        'code3DS'      => $code3dsForChallenge,
                        'validateToken' => $validateToken,
                    ]);

                    if ($validateResp->isSuccess()) {
                        echo "      ✅ Validação aprovada!\n";
                        echo "      ℹ️  paymentId: " . ($validateResp->getPaymentId() ?: 'N/D') . "\n";
                        echo "      ℹ️  status: " . ($validateResp->get('paymentAuthorization.statusDescription') ?: 'N/D') . "\n";
                        echo "      ℹ️  returnCode: " . ($validateResp->getReturnCode() ?: 'N/D') . "\n";
                    } else {
                        echo "      ❌ Validação REJEITADA!\n";
                        echo "      ℹ️  errorDescription: " . $validateResp->getErrorDescription() . "\n";
                        echo "      ℹ️  returnCode: " . ($validateResp->getReturnCode() ?: 'N/D') . "\n";
                    }
                    echo "\n";
                } catch (\Exception $validateEx) {
                    echo "      ❌ EXCEPTION no validate:\n";
                    echo "      {$validateEx->getMessage()}\n\n";
                }
            } else {
                echo "      ❌ Pagamento não retornou Challenge (inesperado)\n\n";
            }
        }
    } catch (\Exception $e) {
        echo "   ❌ Exceção: " . $e->getMessage() . "\n\n";
    }

    echo "✨ Testes concluídos!\n";

} catch (\Exception $e) {
    echo "❌ Erro ao inicializar SDK:\n";
    echo "   " . $e->getMessage() . "\n";
    echo "\nVerifique:\n";
    echo "   1. Credenciais estão corretas?\n";
    echo "   2. composer dump-autoload foi executado?\n";
}
