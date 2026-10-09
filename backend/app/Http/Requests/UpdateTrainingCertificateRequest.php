<?php

namespace App\Http\Requests;

/**
 * Corregir valida igual que crear. El consecutivo, el codigo de verificacion y
 * la fecha de expedicion se conservan: un certificado ya impreso debe seguir
 * verificando con su mismo QR.
 */
class UpdateTrainingCertificateRequest extends StoreTrainingCertificateRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules();
    }
}
