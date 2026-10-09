<?php

namespace App\Services;

use App\Models\BillingAccount;
use App\Models\BillingCompany;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Emite y corrige cuentas de cobro: consecutivo de cinco digitos, copia de la
 * empresa y de los items, y total calculado en el servidor.
 */
class BillingAccountGenerator
{
    private const RELATIONS = ['creator', 'company'];

    public function __construct(private readonly OrderNumberGenerator $numbers) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $user = null): BillingAccount
    {
        return DB::transaction(function () use ($data, $user) {
            $company = $this->rememberCompany($data);
            $number = $this->numbers->next(OrderNumberGenerator::BILLING_KEY);

            $account = BillingAccount::create([
                ...$this->attributes($data, $company),
                'number' => $number,
                'number_code' => self::formatNumber($number),
                'created_by' => $user?->id,
            ]);

            return $account->load(self::RELATIONS);
        });
    }

    /**
     * Corrige conservando el consecutivo: una cuenta ya enviada se identifica
     * por su numero.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BillingAccount $account, array $data): BillingAccount
    {
        return DB::transaction(function () use ($account, $data) {
            $company = $this->rememberCompany($data);

            $account->update($this->attributes($data, $company));

            return $account->fresh(self::RELATIONS);
        });
    }

    /** Consecutivo de cinco digitos, como "06010" en el documento de guia. */
    public static function formatNumber(int $number): string
    {
        return str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Calcula el subtotal de cada item y el total. Se hace en el servidor: lo
     * que llegue del navegador es solo cantidad y precio.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public static function priced(array $items): array
    {
        $priced = [];
        $total = 0;

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = (int) $item['unit_price'];
            $subtotal = $quantity * $unitPrice;
            $total += $subtotal;

            $priced[] = [
                'concept' => trim((string) $item['concept']),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }

        return ['items' => $priced, 'total' => $total];
    }

    /**
     * Guarda la empresa para poder elegirla la proxima vez. El NIT la
     * identifica: si ya existe, se actualizan sus datos en vez de duplicarla.
     *
     * @param  array<string, mixed>  $data
     */
    private function rememberCompany(array $data): BillingCompany
    {
        return BillingCompany::updateOrCreate(
            ['nit' => $data['company_nit']],
            [
                'name' => $data['company_name'],
                'address' => $data['company_address'],
                'city' => $data['company_city'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, BillingCompany $company): array
    {
        $priced = self::priced($data['items']);

        return [
            'issue_place' => $data['issue_place'],
            'issue_date' => $data['issue_date'],

            'billing_company_id' => $company->id,
            // Copia: corregir la empresa no debe cambiar una cuenta emitida.
            'company_name' => $data['company_name'],
            'company_nit' => $data['company_nit'],
            'company_address' => $data['company_address'],
            'company_city' => $data['company_city'],

            'items' => $priced['items'],
            'total' => $priced['total'],
        ];
    }
}
