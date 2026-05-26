<?php

namespace Adiq\Auth;

/**
 * Contrato para provedores de credencial OAuth2.
 *
 * Implemente esta interface para fornecer credenciais a partir de cofre,
 * AWS Secrets Manager, Vault HashiCorp, etc — em vez de hardcoded no Config.
 */
interface CredentialsInterface
{
    /** @return string */
    public function getClientId();

    /** @return string */
    public function getClientSecret();
}
