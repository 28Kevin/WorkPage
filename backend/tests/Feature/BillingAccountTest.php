<?php

namespace Tests\Feature;

use App\Models\BillingCompany;
use App\Models\BillingConcept;
use App\Models\User;
use App\Support\MoneyInWords;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cuentas de cobro: consecutivo de cinco digitos, totales calculados en el
 * servidor, empresas reutilizables y PDF calcado del documento de guia.
 */
class BillingAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'issue_place' => 'Bogotá',
            'issue_date' => '2026-09-24',
            'company_name' => 'FUMIGACIONES OCAMPO S A S',
            'company_nit' => '900867292-1',
            'company_address' => 'CR 3 2 52',
            'company_city' => 'Facatativá',
            'items' => [
                ['concept' => 'Certificado de capacitación para trabajo seguro en alturas',
                    'quantity' => 1, 'unit_price' => 1000000],
                ['concept' => 'Certificado de capacitación para entrante en espacios confinados',
                    'quantity' => 2, 'unit_price' => 1080000],
            ],
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function create(array $overrides = []): array
    {
        return $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/billing', $this->payload($overrides))
            ->assertCreated()
            ->json('data');
    }

    public function test_the_module_is_restricted_to_authenticated_users(): void
    {
        $this->getJson('/api/billing')->assertUnauthorized();
        $this->postJson('/api/billing', $this->payload())->assertUnauthorized();
        $this->getJson('/api/billing/form')->assertUnauthorized();
    }

    public function test_the_form_offers_the_seven_concepts_and_the_next_number(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/billing/form')
            ->assertOk();

        $this->assertCount(7, $response->json('concepts'));
        $this->assertSame(
            'Certificado de capacitación para trabajo seguro en alturas',
            $response->json('concepts.0.name'),
        );
        $this->assertSame(
            'Certificado de manipulación de alimentos',
            $response->json('concepts.6.name'),
        );

        // Cinco digitos, continuando donde iba la numeracion a mano.
        $this->assertSame('06013', $response->json('next_number_code'));
    }

    public function test_the_number_is_a_five_digit_consecutive_that_never_repeats(): void
    {
        $first = $this->create();
        $second = $this->create(['company_nit' => '800123456-7']);

        $this->assertSame('06013', $first['number_code']);
        $this->assertSame('06014', $second['number_code']);
        $this->assertNotSame($first['number_code'], $second['number_code']);
    }

    public function test_the_subtotals_and_the_total_are_calculated_by_the_server(): void
    {
        // 1 x 1.000.000 + 2 x 1.080.000 = 3.160.000, el total del documento guía.
        $account = $this->create();

        $this->assertSame(1000000, $account['items'][0]['subtotal']);
        $this->assertSame(2160000, $account['items'][1]['subtotal']);
        $this->assertSame(3160000, $account['total']);

        $this->assertSame('$3’160.000', $account['total_label']);
        $this->assertSame(
            'Tres millones ciento sesenta mil pesos M/CTE',
            $account['total_in_words'],
        );
    }

    public function test_a_total_sent_from_the_browser_is_ignored(): void
    {
        // El navegador muestra el total, pero el que vale es el del servidor.
        $account = $this->create(['total' => 1]);

        $this->assertSame(3160000, $account['total']);
    }

    public function test_creation_validates_the_form_and_requires_at_least_one_item(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/billing', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'issue_place', 'issue_date', 'company_name', 'company_nit',
                'company_address', 'company_city', 'items',
            ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/billing', $this->payload(['items' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/billing', $this->payload([
                'items' => [['concept' => 'Algo', 'quantity' => 0, 'unit_price' => 100]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_the_company_is_saved_to_choose_it_next_time(): void
    {
        $this->create();

        $this->assertDatabaseHas('billing_companies', [
            'nit' => '900867292-1',
            'name' => 'FUMIGACIONES OCAMPO S A S',
        ]);

        // La segunda cuenta a la misma empresa la actualiza, no la duplica.
        $this->create(['company_address' => 'CR 3 2 90']);

        $this->assertSame(1, BillingCompany::where('nit', '900867292-1')->count());
        $this->assertSame(
            'CR 3 2 90',
            BillingCompany::where('nit', '900867292-1')->value('address'),
        );

        $companies = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/billing/form')
            ->json('companies');

        $this->assertSame('900867292-1', $companies[0]['nit']);
    }

    public function test_correcting_a_company_does_not_change_an_issued_account(): void
    {
        $account = $this->create();

        // Se emite otra cuenta con la direccion corregida.
        $this->create(['company_address' => 'CALLE NUEVA 10 20']);

        $stored = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/billing/{$account['id']}")
            ->assertOk()
            ->json('data');

        // La primera conserva la direccion con la que se emitio.
        $this->assertSame('CR 3 2 52', $stored['company']['address']);
    }

    public function test_renaming_a_concept_does_not_change_an_issued_account(): void
    {
        $account = $this->create();

        $concept = BillingConcept::where('name', 'like', '%trabajo seguro en alturas%')->first();

        $this->actingAs($this->admin(), 'sanctum')
            ->patchJson("/api/billing/concepts/{$concept->id}", ['name' => 'Otro nombre'])
            ->assertOk();

        $stored = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/billing/{$account['id']}")
            ->json('data');

        $this->assertSame(
            'Certificado de capacitación para trabajo seguro en alturas',
            $stored['items'][0]['concept'],
        );
    }

    public function test_concepts_can_be_added_and_retired_from_the_dropdown(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/billing/concepts', ['name' => 'Asesoría en alturas'])
            ->assertCreated();

        $this->assertCount(8, $this->actingAs($admin, 'sanctum')->getJson('/api/billing/form')->json('concepts'));

        $concept = BillingConcept::where('name', 'Asesoría en alturas')->first();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/billing/concepts/{$concept->id}")
            ->assertOk();

        // Sale del desplegable pero no se borra: el historial lo referencia.
        $this->assertCount(7, $this->actingAs($admin, 'sanctum')->getJson('/api/billing/form')->json('concepts'));
        $this->assertDatabaseHas('billing_concepts', ['id' => $concept->id, 'active' => false]);
    }

    public function test_the_history_is_searchable_by_company_and_by_number(): void
    {
        $this->create();
        $this->create(['company_nit' => '800123456-7', 'company_name' => 'CONSTRUCTORA ANDINA S.A.S.']);

        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/billing?search=ANDINA')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company.name', 'CONSTRUCTORA ANDINA S.A.S.');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/billing?search=06013')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number_code', '06013');
    }

    public function test_an_account_can_be_corrected_keeping_its_number(): void
    {
        $account = $this->create();

        $updated = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/billing/{$account['id']}", $this->payload([
                'items' => [['concept' => 'Certificado de manipulación de alimentos',
                    'quantity' => 3, 'unit_price' => 50000]],
            ]))
            ->assertOk()
            ->json('data');

        $this->assertSame($account['number_code'], $updated['number_code']);
        $this->assertSame(150000, $updated['total']);
        $this->assertSame('Ciento cincuenta mil pesos M/CTE', $updated['total_in_words']);
    }

    public function test_an_annulled_account_cannot_be_edited_and_keeps_its_number(): void
    {
        $account = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/billing/{$account['id']}", ['reason' => 'Emitida por error'])
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/billing/{$account['id']}", $this->payload())
            ->assertConflict();

        // El consecutivo no se reutiliza.
        $next = $this->create(['company_nit' => '800123456-7']);
        $this->assertSame('06014', $next['number_code']);
    }

    public function test_the_pdf_follows_the_guide_document(): void
    {
        $account = $this->create();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/billing/{$account['id']}/pdf");

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        // Hoja carta vertical y una sola pagina.
        $this->assertSame(1, preg_match_all('~/Type\s*/Page[^s]~', $content));
    }

    public function test_the_downloaded_file_is_named_after_the_number_and_the_company(): void
    {
        $account = $this->create();

        $disposition = (string) $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/billing/{$account['id']}/pdf")
            ->headers->get('content-disposition');

        // Nombre en ASCII: la cabecera trae una sola variante.
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString(
            'filename="Cuenta de cobro 06013 - FUMIGACIONES OCAMPO S A S.pdf"',
            $disposition,
        );
    }

    public function test_an_accented_company_name_survives_in_the_file_name(): void
    {
        $account = $this->create(['company_name' => 'Construcciones Pérez & Muñoz S.A.S.']);

        $disposition = (string) $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/billing/{$account['id']}/pdf")
            ->headers->get('content-disposition');

        // Con tildes aparece tambien la variante UTF-8, que es la que conserva
        // los acentos; la ASCII queda de respaldo.
        $expected = rawurlencode('Cuenta de cobro 06013 - Construcciones Pérez & Muñoz S.A.S..pdf');

        $this->assertStringContainsString("filename*=utf-8''{$expected}", $disposition);
    }

    public function test_the_amount_endpoint_spells_what_the_document_will_print(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/billing/amount?total=3160000')
            ->assertOk()
            ->assertJsonPath('total_label', '$3’160.000')
            ->assertJsonPath('total_in_words', 'Tres millones ciento sesenta mil pesos M/CTE');
    }

    public function test_the_issuer_data_is_ready_without_configuring_anything(): void
    {
        $biller = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/branding')
            ->assertOk()
            ->json('branding.biller');

        $this->assertSame('MARIA EDER MONTERO CORREA', $biller['name']);
        $this->assertSame('51852142 - 9', $biller['nit']);
        $this->assertSame('51852142', $biller['document']);
        $this->assertSame('3138425731', $biller['mobile']);
        $this->assertSame('mariaederm@gmail.com', $biller['email']);

        // La firma viaja como data URI: la usan el PDF y la vista previa.
        $this->assertStringStartsWith('data:image/png;base64,', $biller['signature']);
    }

    public function test_the_signature_block_of_the_pdf_carries_the_issuer_and_the_signature(): void
    {
        $account = $this->create();

        $pdf = $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/billing/{$account['id']}/pdf")
            ->getContent();

        // La firma es la unica imagen del documento: sin ella no se incrusta
        // ninguna, que es como salia antes de cargar los datos del emisor.
        $this->assertSame(1, preg_match_all('~/Subtype\s*/Image~', $pdf));
    }

    public function test_money_is_written_the_colombian_way(): void
    {
        // Apostrofo para los millones y punto para los miles.
        $this->assertSame('$3’160.000', MoneyInWords::format(3160000));
        $this->assertSame('$50.000', MoneyInWords::format(50000));
        $this->assertSame('$1.234’567.890', MoneyInWords::format(1234567890));

        // "de pesos" solo cuando la cifra termina en millon.
        $this->assertSame('Un millón de pesos M/CTE', MoneyInWords::spell(1000000));
        $this->assertSame('Dos millones de pesos M/CTE', MoneyInWords::spell(2000000));
        $this->assertSame('Un millón quinientos mil pesos M/CTE', MoneyInWords::spell(1500000));
    }
}
