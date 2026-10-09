<?php

namespace App\Services;

use App\Models\FoodHandlerRecord;
use App\Models\User;
use App\Support\FoodHandlerForm;
use Illuminate\Support\Facades\DB;

/**
 * Crea y corrige el paquete de manipulacion de alimentos: consecutivo propio,
 * codigo de verificacion y precarga de los valores normales de laboratorio.
 */
class FoodHandlerRecordGenerator
{
    private const RELATIONS = ['creator'];

    public function __construct(
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly VerificationCodeGenerator $verificationCodes,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $user = null): FoodHandlerRecord
    {
        return DB::transaction(function () use ($data, $user) {
            $orderNumber = $this->orderNumbers->next(OrderNumberGenerator::FOOD_HANDLER_KEY);

            $record = FoodHandlerRecord::create([
                ...$this->attributes($data),
                'order_number' => $orderNumber,
                'order_code' => $this->orderNumbers->format(
                    $orderNumber,
                    (int) date('Y', strtotime($data['issue_date'])),
                    OrderNumberGenerator::FOOD_HANDLER_PREFIX,
                ),
                'verification_code' => $this->verificationCodes->generate(),
                'issued_at' => now(),
                'created_by' => $user?->id,
            ]);

            return $record->load(self::RELATIONS);
        });
    }

    /**
     * Corrige el documento conservando consecutivo, codigo de verificacion y
     * fecha de expedicion: el QR ya impreso debe seguir sirviendo.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(FoodHandlerRecord $record, array $data): FoodHandlerRecord
    {
        return DB::transaction(function () use ($record, $data) {
            $record->update($this->attributes($data));

            return $record->fresh(self::RELATIONS);
        });
    }

    /**
     * Borrador que abre el formulario: todos los examenes en su valor normal,
     * para confirmar o corregir en vez de escribirlos desde cero.
     *
     * @return array<string, mixed>
     */
    public function draft(): array
    {
        return ['lab_results' => FoodHandlerForm::normalResults()];
    }

    /**
     * Campos comunes a crear y corregir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'issue_date' => $data['issue_date'],
            'full_name' => $data['full_name'],
            'document_number' => $data['document_number'],
            'age' => (int) $data['age'],
            'photo' => $data['photo'] ?? null,
            // Lo que no venga queda en su valor normal: ningun campo sale vacio.
            'lab_results' => FoodHandlerForm::mergeResults($data['lab_results'] ?? []),
        ];
    }
}
