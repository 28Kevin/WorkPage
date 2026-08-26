<?php

namespace Tests\Feature;

use App\Models\Arl;
use App\Models\City;
use App\Models\Eps;
use App\Models\Risk;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MedicalExamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'email' => 'admin@centromedico.test',
            'password' => Hash::make('password'),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Carlos Andrés Rodríguez Pérez',
            'document_type' => 'CC',
            'document_number' => '1020304050',
            'birth_date' => '1990-05-14',
            'sex' => 'M',
            'email' => 'carlos.rodriguez@example.com',
            'phone' => '3101234567',
            'height_cm' => 175,
            'is_independent' => false,
            'company_name' => 'Constructora Andina S.A.S.',
            'company_nit' => '830.111.222-3',
            'eps_id' => Eps::first()->id,
            'arl_id' => Arl::first()->id,
            'city_id' => City::first()->id,
            'position' => 'Oficial de obra',
            'risk_ids' => Risk::limit(2)->pluck('id')->all(),
            'exam_date' => now()->toDateString(),
            'exam_type' => 'ingreso',
            'aptitude_position' => 'APTO',
            'aptitude_heights' => 'APTO',
            'aptitude_confined' => 'APTO',
            'consent_accepted' => true,
        ], $overrides);
    }

    public function test_admin_can_log_in_and_receive_a_token(): void
    {
        $this->admin();

        $this->postJson('/api/auth/login', [
            'email' => 'admin@centromedico.test',
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        $this->admin();

        $this->postJson('/api/auth/login', [
            'email' => 'admin@centromedico.test',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_admin_module_is_restricted_to_authenticated_users(): void
    {
        $this->getJson('/api/exams')->assertUnauthorized();
        $this->postJson('/api/exams', $this->payload())->assertUnauthorized();
        $this->getJson('/api/catalogs')->assertUnauthorized();
    }

    public function test_catalogs_expose_eps_arl_cities_risks_and_exam_types(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/catalogs')
            ->assertOk()
            ->assertJsonStructure([
                'eps' => [['id', 'name']],
                'arls' => [['id', 'name', 'certificate_url']],
                'cities' => [['id', 'name', 'department']],
                'risks' => [['id', 'name', 'slug']],
                'afps' => [['id', 'name']],
                'exam_types' => [['value', 'label']],
                'document_types' => [['value', 'label']],
                'sexes' => [['value', 'label']],
                'aptitude_results' => [['value', 'label']],
                'form' => [
                    'systems' => [['key', 'label', 'normal_label', 'findings_label']],
                    'paraclinicals' => [['key', 'label']],
                    'assessments' => [['key', 'label']],
                    'aptitudes' => [['key', 'label']],
                ],
            ])
            ->assertJsonCount(6, 'exam_types')
            ->assertJsonCount(7, 'form.paraclinicals')
            ->assertJsonCount(3, 'form.aptitudes');
    }

    public function test_arl_catalog_links_to_each_certificate_page(): void
    {
        $arls = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/catalogs')
            ->assertOk()
            ->json('arls');

        $links = collect($arls)->pluck('certificate_url', 'name');

        $this->assertSame('https://www.sura.co/arl/afiliacion/consulta', $links['ARL SURA']);
        $this->assertSame('https://operacionesarl.positiva.gov.co', $links['Positiva Compañía de Seguros']);
        $this->assertSame('https://www.segurosbolivar.com/arl', $links['Seguros Bolívar ARL']);

        foreach ($links as $url) {
            $this->assertStringStartsWith('https://', (string) $url);
        }
    }

    public function test_retired_insurers_are_hidden_but_not_deleted(): void
    {
        // Simula una base sembrada antes de retirarlas.
        Arl::create(['name' => 'Liberty Seguros ARL', 'active' => true]);
        Arl::create(['name' => 'Mapfre Seguros ARL', 'active' => true]);

        $this->seed(CatalogSeeder::class);

        $names = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/catalogs')
            ->assertOk()
            ->collect('arls')
            ->pluck('name');

        // Liberty no es una ARL y Mapfre ya no opera en Colombia.
        $this->assertNotContains('Liberty Seguros ARL', $names);
        $this->assertNotContains('Mapfre Seguros ARL', $names);
        $this->assertCount(5, $names);

        // Siguen en la base: hay examenes emitidos que las referencian.
        $this->assertDatabaseHas('arls', ['name' => 'Liberty Seguros ARL', 'active' => false]);
        $this->assertDatabaseHas('arls', ['name' => 'Mapfre Seguros ARL', 'active' => false]);
    }

    public function test_ideal_weight_is_calculated_from_height(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/tools/ideal-weight?height_cm=175')
            ->assertOk()
            ->assertJson([
                'height_cm' => 175,
                'ideal_weight_kg' => 67.4, // 22 * 1.75^2
                'min_weight_kg' => 56.7,
                'max_weight_kg' => 76.3,
            ]);
    }

    public function test_exam_is_created_with_autofilled_medical_parameters_and_apto_result(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload())
            ->assertCreated();

        $response->assertJsonPath('data.exam.result', 'APTO');
        $response->assertJsonPath('data.worker.ideal_weight_kg', 67.4);
        $response->assertJsonStructure([
            'data' => [
                'order_number', 'order_code',
                'worker' => ['full_name', 'document_number', 'age', 'height_cm', 'ideal_weight_kg'],
                'occupational' => ['company_name', 'eps', 'arl', 'city', 'risks'],
                'exam' => ['exam_date', 'exam_type_label', 'result_label', 'recommendations'],
                'medical_parameters' => [
                    'vitals', 'anthropometry', 'vision', 'systems', 'assessments', 'history',
                ],
                'paraclinicals' => [['key', 'label', 'performed', 'status', 'result']],
                'aptitudes' => [['key', 'label', 'value', 'value_label']],
                'verification' => ['code', 'url'],
                'pdf_url',
            ],
        ]);

        $parameters = $response->json('data.medical_parameters');
        $this->assertSame('Normal', $parameters['anthropometry']['bmi_classification']);
        $this->assertGreaterThanOrEqual(18.5, $parameters['anthropometry']['bmi']);
        $this->assertLessThanOrEqual(24.9, $parameters['anthropometry']['bmi']);
        $this->assertGreaterThanOrEqual(105, $parameters['vitals']['systolic']);
        $this->assertLessThanOrEqual(125, $parameters['vitals']['systolic']);

        // Los sistemas se precargan como normales y quedan editables.
        $this->assertSame('normal', $parameters['systems']['cardiovascular']);
        $this->assertCount(8, $parameters['systems']);
        $this->assertCount(7, $response->json('data.paraclinicals'));
    }

    public function test_order_numbers_are_consecutive_and_ascending(): void
    {
        $admin = $this->admin();

        $first = $this->actingAs($admin, 'sanctum')->postJson('/api/exams', $this->payload())->json('data');
        $second = $this->actingAs($admin, 'sanctum')->postJson('/api/exams', $this->payload([
            'document_number' => '1020304051',
        ]))->json('data');

        $this->assertSame(1, $first['order_number']);
        $this->assertSame(2, $second['order_number']);
        $this->assertSame('EMO-'.date('Y').'-000001', $first['order_code']);
        $this->assertSame('EMO-'.date('Y').'-000002', $second['order_code']);
    }

    public function test_next_order_number_endpoint_previews_the_upcoming_consecutive(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/exams/next-order-number')
            ->assertOk()
            ->assertJsonPath('next_order_number', 1);

        $this->actingAs($admin, 'sanctum')->postJson('/api/exams', $this->payload());

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/exams/next-order-number')
            ->assertOk()
            ->assertJsonPath('next_order_number', 2);
    }

    public function test_exam_creation_validates_required_fields(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'full_name', 'document_type', 'document_number', 'birth_date', 'sex',
                'height_cm', 'company_name', 'company_nit', 'arl_id',
                'position', 'exam_date', 'exam_type', 'is_independent',
                'aptitude_position', 'aptitude_heights', 'aptitude_confined',
                'consent_accepted',
            ]);
    }

    public function test_exam_type_must_be_one_of_the_allowed_values(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload(['exam_type' => 'inexistente']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('exam_type');
    }

    public function test_pdf_is_downloadable_and_contains_a_qr_code(): void
    {
        $admin = $this->admin();
        $exam = $this->actingAs($admin, 'sanctum')->postJson('/api/exams', $this->payload())->json('data');

        $response = $this->actingAs($admin, 'sanctum')->get("/api/exams/{$exam['id']}/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(20000, strlen($content), 'El PDF debería incluir la imagen del QR.');
    }

    public function test_exam_list_is_searchable_by_document_and_ordered_by_consecutive(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum')->postJson('/api/exams', $this->payload());
        $this->actingAs($admin, 'sanctum')->postJson('/api/exams', $this->payload([
            'document_number' => '9999999',
            'full_name' => 'Ana María Torres Gil',
        ]));

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/exams?search=9999999')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document_number', '9999999');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/exams')
            ->assertOk()
            ->assertJsonPath('data.0.order_code', 'EMO-'.date('Y').'-000002');
    }
}
