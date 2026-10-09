<?php

namespace App\Http\Requests;

/**
 * Corregir valida igual que crear. El consecutivo no se edita: una cuenta ya
 * enviada se identifica por su numero.
 */
class UpdateBillingAccountRequest extends StoreBillingAccountRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules();
    }
}
