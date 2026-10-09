<?php

namespace App\Http\Requests;

/**
 * La correccion valida igual que la creacion. El consecutivo, el codigo de
 * verificacion y la fecha de expedicion no se editan: se conservan para que un
 * carnet ya impreso siga verificando.
 */
class UpdateFoodHandlerRecordRequest extends StoreFoodHandlerRecordRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules();
    }
}
