<?php

namespace Tests\Feature;

use App\Models\Afp;
use App\Models\Arl;
use App\Models\City;
use App\Models\Eps;
use App\Models\Risk;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Campos que el formato de evaluacion ocupacional agrega al examen. */
class OccupationalFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'Ana Maria Torres Gil',
            'document_type' => 'CC',
            'document_number' => '1020304050',
            'birth_date' => '1992-03-11',
            'sex' => 'F',
            'email' => 'ana.torres@example.com',
            'phone' => '3109998877',
            'height_cm' => 165,
            'eps_id' => Eps::first()->id,
            'arl_id' => Arl::first()->id,
            'afp_id' => Afp::first()->id,
            'is_independent' => false,
            'company_name' => 'Montajes del Norte S.A.S.',
            'company_nit' => '901.222.333-4',
            'client_company' => 'Refineria Costa Azul',
            'economic_activity' => 'Montaje de estructuras metalicas',
            'city_id' => City::first()->id,
            'position' => 'Tecnica de mantenimiento',
            'risk_ids' => [Risk::where('slug', 'trabajo-en-alturas')->value('id')],
            'exam_date' => now()->toDateString(),
            'exam_type' => 'periodico',
            'aptitude_position' => 'APTO',
            'aptitude_heights' => 'APTO',
            'aptitude_confined' => 'APTO',
            'consent_accepted' => true,
        ], $overrides);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_draft_returns_normal_values_ready_to_review(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/exams/draft?height_cm=165')
            ->assertOk()
            ->assertJsonStructure([
                'medical_parameters' => ['vitals', 'anthropometry', 'vision', 'systems', 'assessments'],
                'paraclinicals',
            ]);

        $this->assertSame('normal', $response->json('medical_parameters.systems.cardiovascular'));
        $this->assertTrue($response->json('paraclinicals.audiometria.performed'));
        $this->assertCount(7, $response->json('paraclinicals'));
    }

    public function test_draft_works_before_the_worker_is_measured(): void
    {
        // El formulario lo pide al abrirse, cuando aun no hay estatura.
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/exams/draft')
            ->assertOk();

        $vitals = $response->json('medical_parameters.vitals');

        $this->assertGreaterThanOrEqual(105, $vitals['systolic']);
        $this->assertLessThanOrEqual(125, $vitals['systolic']);
        $this->assertGreaterThanOrEqual(62, $vitals['heart_rate']);
        $this->assertLessThanOrEqual(84, $vitals['heart_rate']);
        $this->assertGreaterThanOrEqual(96, $vitals['spo2']);

        $this->assertSame('20/20', $response->json('medical_parameters.vision.right_eye'));
    }

    public function test_each_draft_returns_different_values_within_normal_ranges(): void
    {
        $admin = $this->admin();

        $draws = collect(range(1, 8))->map(
            fn () => $this->actingAs($admin, 'sanctum')
                ->getJson('/api/exams/draft')
                ->json('medical_parameters.vitals.systolic'),
        );

        // Aleatorio pero siempre dentro del rango normal.
        $this->assertGreaterThan(1, $draws->unique()->count());
        $this->assertTrue($draws->every(fn (int $value) => $value >= 105 && $value <= 125));
    }

    public function test_draft_rejects_an_out_of_range_height(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/exams/draft?height_cm=90')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('height_cm');
    }

    public function test_new_identification_and_employer_fields_are_stored(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload())
            ->assertCreated();

        $response->assertJsonPath('data.worker.document_type', 'CC')
            ->assertJsonPath('data.worker.sex_label', 'Femenino')
            ->assertJsonPath('data.occupational.client_company', 'Refineria Costa Azul')
            ->assertJsonPath('data.occupational.economic_activity', 'Montaje de estructuras metalicas')
            ->assertJsonPath('data.occupational.afp.name', Afp::first()->name);
    }

    public function test_overall_result_is_the_most_restrictive_of_the_three_concepts(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload([
                'aptitude_position' => 'APTO',
                'aptitude_heights' => 'APTO_CON_RESTRICCIONES',
                'aptitude_confined' => 'APLAZADO',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.exam.result', 'APLAZADO')
            ->assertJsonPath('data.aptitudes.0.value', 'APTO')
            ->assertJsonPath('data.aptitudes.1.value', 'APTO_CON_RESTRICCIONES')
            ->assertJsonPath('data.aptitudes.2.value', 'APLAZADO');
    }

    public function test_the_three_aptitude_concepts_are_always_required(): void
    {
        $payload = $this->payload();
        unset($payload['aptitude_heights'], $payload['aptitude_confined']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['aptitude_heights', 'aptitude_confined']);
    }

    public function test_physical_exam_overrides_replace_the_preloaded_values(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload([
                'vitals' => ['systolic' => 138, 'diastolic' => 88],
                'vision' => ['right_eye' => '20/40', 'optical_correction' => true],
                'systems' => ['cardiovascular' => 'hallazgos'],
                'clinical_findings' => 'Soplo sistolico grado II en foco aortico.',
            ]))
            ->assertCreated();

        $parameters = $response->json('data.medical_parameters');

        $this->assertSame(138, $parameters['vitals']['systolic']);
        $this->assertSame('20/40', $parameters['vision']['right_eye']);
        $this->assertTrue($parameters['vision']['optical_correction']);
        $this->assertSame('hallazgos', $parameters['systems']['cardiovascular']);

        // Lo que no se envio conserva el valor precargado.
        $this->assertSame('normal', $parameters['systems']['gait']);
        $this->assertSame('20/20', $parameters['vision']['left_eye']);

        $response->assertJsonPath('data.exam.clinical_findings', 'Soplo sistolico grado II en foco aortico.');
    }

    public function test_paraclinicals_can_be_marked_as_not_performed(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload([
                'paraclinicals' => [
                    'espirometria' => ['performed' => false, 'status' => 'normal', 'result' => ''],
                    'glicemia' => ['performed' => true, 'status' => 'alterada', 'result' => '132 mg/dL'],
                ],
            ]))
            ->assertCreated();

        $paraclinicals = collect($response->json('data.paraclinicals'))->keyBy('key');

        $this->assertFalse($paraclinicals['espirometria']['performed']);
        $this->assertSame('alterada', $paraclinicals['glicemia']['status']);
        $this->assertSame('132 mg/dL', $paraclinicals['glicemia']['result']);
        $this->assertTrue($paraclinicals['audiometria']['performed']);
    }

    public function test_unknown_keys_are_rejected_in_the_semi_structured_blocks(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload([
                'systems' => ['inventado' => 'normal'],
                'paraclinicals' => ['resonancia' => ['performed' => true, 'status' => 'normal']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['systems', 'paraclinicals']);
    }

    public function test_system_status_only_accepts_normal_or_findings(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload(['systems' => ['gait' => 'regular']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('systems.gait');
    }

    public function test_refused_consent_is_recorded(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload(['consent_accepted' => false]))
            ->assertCreated()
            ->assertJsonPath('data.exam.consent_accepted', false);
    }

    public function test_restrictions_and_validity_travel_to_the_certificate(): void
    {
        $exam = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload([
                'aptitude_position' => 'APTO_CON_RESTRICCIONES',
                'restrictions' => 'Evitar levantamiento de cargas superiores a 15 kg.',
                'restrictions_validity' => '6 meses',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.exam.restrictions_validity', '6 meses')
            ->json('data');

        $pdf = $this->actingAs($this->admin(), 'sanctum')->get("/api/exams/{$exam['id']}/pdf");

        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }
}
