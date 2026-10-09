<?php

namespace Tests\Feature;

use App\Models\FoodHandlerRecord;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel administrativo del paquete de manipulacion de alimentos: crear,
 * listar, corregir, anular, previsualizar y descargar.
 */
class FoodHandlerPanelTest extends TestCase
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
            'issue_date' => now()->toDateString(),
            'full_name' => 'Luz Marina Quintero Ávila',
            'document_number' => '1020304050',
            'age' => 34,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function create(array $overrides = []): array
    {
        return $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/food-handler', $this->payload($overrides))
            ->assertCreated()
            ->json('data');
    }

    public function test_the_module_is_restricted_to_authenticated_users(): void
    {
        $this->getJson('/api/food-handler')->assertUnauthorized();
        $this->postJson('/api/food-handler', $this->payload())->assertUnauthorized();
        $this->getJson('/api/food-handler/draft')->assertUnauthorized();
    }

    public function test_the_draft_offers_the_catalog_with_every_value_preloaded(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/food-handler/draft')
            ->assertOk()
            ->assertJsonStructure([
                'lab_catalog' => [['key', 'label', 'page', 'signature', 'groups']],
                'lab_results',
            ]);

        // Los cinco examenes del formato, con sus grupos y campos.
        $this->assertCount(5, $response->json('lab_catalog'));
        $this->assertSame('No reactivo', $response->json('lab_results.vdrl.resultado.resultado'));
        $this->assertSame('Negativo', $response->json('lab_results.uroanalisis.quimico.proteinas'));
    }

    public function test_a_document_is_created_with_its_own_serial_and_labelled_results(): void
    {
        $record = $this->create();

        $this->assertSame('MA-'.date('Y').'-000001', $record['order_code']);
        $this->assertSame(FoodHandlerRecord::TYPE, $record['document_type']);
        $this->assertSame('Luz Marina Quintero Ávila', $record['patient']['full_name']);

        // Los resultados vuelven con etiqueta y valor, en el orden del formato.
        $panels = collect($record['lab_results']);
        $this->assertSame(
            ['frotis_garganta', 'koh', 'coprologico', 'vdrl', 'uroanalisis'],
            $panels->pluck('key')->all(),
        );

        $vdrl = $panels->firstWhere('key', 'vdrl');
        $field = collect($vdrl['groups'][0]['fields'])->firstWhere('key', 'resultado');
        $this->assertSame('Resultado', $field['label']);
        $this->assertSame('No reactivo', $field['value']);

        $this->assertNotEmpty($record['verification']['code']);
        $this->assertStringContainsString('/verificar/', $record['verification']['url']);
    }

    public function test_creation_validates_the_patient_data(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/food-handler', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['issue_date', 'full_name', 'document_number', 'age']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/food-handler', $this->payload(['document_number' => 'ABC123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_number');
    }

    public function test_an_invented_lab_key_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/food-handler', $this->payload([
                'lab_results' => ['resonancia' => ['resultado' => ['resultado' => 'Normal']]],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lab_results');
    }

    public function test_a_real_result_is_stored_and_the_rest_stays_normal(): void
    {
        $record = $this->create([
            'lab_results' => ['coprologico' => ['microscopico' => ['parasitos' => 'Quistes de Giardia lamblia']]],
        ]);

        $copro = collect($record['lab_results'])->firstWhere('key', 'coprologico');
        $micro = collect($copro['groups'])->firstWhere('key', 'microscopico');

        $this->assertSame(
            'Quistes de Giardia lamblia',
            collect($micro['fields'])->firstWhere('key', 'parasitos')['value'],
        );
        $this->assertSame(
            'No se observan',
            collect($micro['fields'])->firstWhere('key', 'leucocitos')['value'],
        );
    }

    public function test_the_next_serial_is_previewed_before_creating(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/food-handler/next-order-number')
            ->assertOk()
            ->assertJsonPath('next_order_number', 1)
            ->assertJsonPath('next_order_code', 'MA-'.date('Y').'-000001');

        $this->actingAs($admin, 'sanctum')->postJson('/api/food-handler', $this->payload());

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/food-handler/next-order-number')
            ->assertOk()
            ->assertJsonPath('next_order_number', 2);
    }

    public function test_editing_keeps_the_serial_and_the_verification_code(): void
    {
        $record = $this->create();

        $updated = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/food-handler/{$record['id']}", $this->payload([
                'age' => 35,
                'lab_results' => ['vdrl' => ['resultado' => ['resultado' => 'Reactivo']]],
            ]))
            ->assertOk()
            ->json('data');

        $this->assertSame($record['order_code'], $updated['order_code']);
        $this->assertSame($record['verification']['code'], $updated['verification']['code']);
        $this->assertSame($record['issued_at'], $updated['issued_at']);
        $this->assertSame(35, $updated['patient']['age']);
    }

    public function test_annulling_keeps_the_record_and_needs_a_reason(): void
    {
        $record = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/food-handler/{$record['id']}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/food-handler/{$record['id']}", ['reason' => 'Documento emitido por error'])
            ->assertOk()
            ->assertJsonPath('data.annulment.annulled', true);

        $this->assertDatabaseHas('food_handler_records', ['id' => $record['id']]);
    }

    public function test_an_annulled_document_cannot_be_edited_or_annulled_again(): void
    {
        $record = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/food-handler/{$record['id']}", ['reason' => 'Datos incorrectos'])
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/food-handler/{$record['id']}", $this->payload())
            ->assertConflict();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/food-handler/{$record['id']}", ['reason' => 'Otro motivo'])
            ->assertConflict();
    }

    public function test_the_listing_hides_annulled_documents_but_can_filter_them(): void
    {
        $record = $this->create();
        $this->create(['document_number' => '9999999', 'full_name' => 'Ana María Torres Gil']);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/food-handler/{$record['id']}", ['reason' => 'Reemplazado'])
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/food-handler')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document_number', '9999999');

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/food-handler?status=annulled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.annulled', true);
    }

    public function test_the_listing_is_searchable_by_name_document_and_serial(): void
    {
        $this->create();
        $this->create(['document_number' => '9999999', 'full_name' => 'Ana María Torres Gil']);

        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/food-handler?search=9999999')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/food-handler?search=MA-'.date('Y').'-000001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.full_name', 'Luz Marina Quintero Ávila');
    }

    public function test_the_pdf_has_the_five_pages_of_the_package(): void
    {
        $record = $this->create();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/food-handler/{$record['id']}/pdf");

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);

        // '/Type /Pages' es el catalogo; las paginas son '/Type /Page' a secas.
        $this->assertSame(5, preg_match_all('~/Type\s*/Page[^s]~', $content));
    }

    public function test_the_downloaded_file_is_named_after_the_patient_and_the_serial(): void
    {
        $record = $this->create();

        $disposition = (string) $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/food-handler/{$record['id']}/pdf")
            ->headers->get('content-disposition');

        $expected = rawurlencode("Luz Marina Quintero Ávila - {$record['order_code']}.pdf");

        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString("filename*=utf-8''{$expected}", $disposition);
    }

    public function test_the_preview_is_served_inline_instead_of_downloading(): void
    {
        $record = $this->create();

        $disposition = (string) $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/food-handler/{$record['id']}/pdf?inline=1")
            ->headers->get('content-disposition');

        $this->assertStringContainsString('inline', $disposition);
    }

    public function test_the_pdf_of_an_annulled_document_is_still_generated(): void
    {
        $record = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/food-handler/{$record['id']}", ['reason' => 'Reemplazado por otro'])
            ->assertOk();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->get("/api/food-handler/{$record['id']}/pdf");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_the_fixed_texts_come_from_the_settings_panel(): void
    {
        $admin = $this->admin();

        $current = $this->actingAs($admin, 'sanctum')->getJson('/api/branding')->json('branding');

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/branding', [
                ...$current,
                'food_handler' => [
                    ...$current['food_handler'],
                    'course_name' => 'Curso especial de manipulación',
                    'course_hours' => '20 horas',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('branding.food_handler.course_name', 'Curso especial de manipulación')
            ->assertJsonPath('branding.food_handler.course_hours', '20 horas');
    }
}
