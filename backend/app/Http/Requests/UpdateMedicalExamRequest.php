<?php

namespace App\Http\Requests;

/**
 * La correccion de un examen valida igual que su creacion. El consecutivo, el
 * codigo de verificacion y la fecha de expedicion no se editan: se conservan
 * para que un PDF ya impreso siga verificando.
 */
class UpdateMedicalExamRequest extends StoreMedicalExamRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules();
    }
}
