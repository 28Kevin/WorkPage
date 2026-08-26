<?php

namespace Tests\Feature;

use App\Models\Arl;
use App\Models\City;
use App\Models\Eps;
use App\Models\MedicalExam;
use App\Models\User;
use App\Services\ExamGenerator;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Trabajador independiente, EPS opcional, correccion y anulacion. */
class ExamLifecycleTest extends TestCase
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
            'full_name' => 'Jorge Enrique Salas Rojas',
            'document_type' => 'CC',
            'document_number' => '7654321',
            'birth_date' => '1988-09-02',
            'sex' => 'M',
            'height_cm' => 178,
            'eps_id' => Eps::first()->id,
            'arl_id' => Arl::first()->id,
            'is_independent' => false,
            'company_name' => 'Alturas Seguras S.A.S.',
            'company_nit' => '900.777.888-1',
            'city_id' => City::first()->id,
            'position' => 'Tecnico de mantenimiento',
            'exam_date' => now()->toDateString(),
            'exam_type' => 'ingreso',
            'aptitude_position' => 'APTO',
            'aptitude_heights' => 'APTO',
            'aptitude_confined' => 'APTO',
            'consent_accepted' => true,
        ], $overrides);
    }

    private function create(array $overrides = []): array
    {
        return $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $this->payload($overrides))
            ->assertCreated()
            ->json('data');
    }

    public function test_contact_and_risks_are_no_longer_required(): void
    {
        $payload = $this->payload();

        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('risk_ids', $payload);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $payload)
            ->assertCreated();
    }

    public function test_the_city_can_be_left_blank(): void
    {
        $exam = $this->create(['city_id' => null]);

        $this->assertNull($exam['occupational']['city']);

        // Ni el listado, ni la verificación pública, ni el PDF deben romperse.
        $this->actingAs($this->admin(), 'sanctum')->getJson('/api/exams')->assertOk();

        $this->getJson("/api/public/verify/{$exam['verification']['code']}")
            ->assertOk()
            ->assertJsonPath('exam.city', null);

        $pdf = $this->actingAs($this->admin(), 'sanctum')->get("/api/exams/{$exam['id']}/pdf");

        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_eps_can_be_left_blank(): void
    {
        $exam = $this->create(['eps_id' => null]);

        $this->assertNull($exam['occupational']['eps']);
    }

    public function test_independent_worker_is_stored_without_nit(): void
    {
        $exam = $this->create([
            'is_independent' => true,
            'company_name' => 'Jorge Enrique Salas Rojas',
            'company_nit' => '900.777.888-1',
        ]);

        $this->assertTrue($exam['occupational']['is_independent']);
        // El NIT se descarta aunque venga en la peticion.
        $this->assertNull($exam['occupational']['company_nit']);
    }

    public function test_nit_is_required_when_the_worker_is_not_independent(): void
    {
        $payload = $this->payload();
        unset($payload['company_nit']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/exams', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_nit');
    }

    public function test_editing_keeps_the_order_number_and_verification_code(): void
    {
        $exam = $this->create();

        $updated = $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/exams/{$exam['id']}", $this->payload([
                'position' => 'Supervisor de alturas',
                'aptitude_confined' => 'APLAZADO',
            ]))
            ->assertOk()
            ->json('data');

        $this->assertSame($exam['order_code'], $updated['order_code']);
        $this->assertSame($exam['order_number'], $updated['order_number']);
        $this->assertSame($exam['verification']['code'], $updated['verification']['code']);
        $this->assertSame($exam['issued_at'], $updated['issued_at']);

        $this->assertSame('Supervisor de alturas', $updated['occupational']['position']);
        // El concepto global se recalcula con los conceptos corregidos.
        $this->assertSame('APLAZADO', $updated['exam']['result']);
    }

    public function test_editing_and_annulling_require_a_session(): void
    {
        // Se crea sin actingAs para que la peticion siguiente salga sin sesion.
        $exam = app(ExamGenerator::class)->create($this->payload(), User::factory()->create());

        $this->putJson("/api/exams/{$exam->id}", $this->payload())->assertUnauthorized();
        $this->deleteJson("/api/exams/{$exam->id}", ['reason' => 'Sin sesion'])->assertUnauthorized();
    }

    public function test_annulling_keeps_the_record_and_needs_a_reason(): void
    {
        $exam = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/exams/{$exam['id']}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/exams/{$exam['id']}", ['reason' => 'Error en el concepto de aptitud'])
            ->assertOk()
            ->assertJsonPath('data.annulment.annulled', true)
            ->assertJsonPath('data.annulment.reason', 'Error en el concepto de aptitud');

        // La fila sigue existiendo: el QR impreso debe seguir respondiendo.
        $this->assertDatabaseHas('medical_exams', ['id' => $exam['id']]);
        $this->assertNotNull(MedicalExam::find($exam['id'])->annulled_at);
    }

    public function test_annulled_exams_leave_the_listing_but_can_be_filtered(): void
    {
        $exam = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/exams/{$exam['id']}", ['reason' => 'Documento emitido por error'])
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/exams')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/exams?status=annulled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.annulled', true);
    }

    public function test_an_annulled_exam_cannot_be_edited_or_annulled_again(): void
    {
        $exam = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/exams/{$exam['id']}", ['reason' => 'Datos incorrectos del trabajador'])
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/exams/{$exam['id']}", $this->payload())
            ->assertConflict();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/exams/{$exam['id']}", ['reason' => 'Otro motivo cualquiera'])
            ->assertConflict();
    }

    public function test_public_verification_reports_an_annulled_document(): void
    {
        $exam = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/exams/{$exam['id']}", ['reason' => 'Concepto corregido en otro documento'])
            ->assertOk();

        $this->getJson("/api/public/verify/{$exam['verification']['code']}")
            ->assertStatus(410)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('annulled', true);

        // Y deja de aparecer en la busqueda por cedula.
        $this->getJson('/api/public/exams/search?document_number=7654321')
            ->assertNotFound()
            ->assertJsonPath('found', false);
    }

    public function test_the_pdf_of_an_annulled_exam_is_still_generated(): void
    {
        $exam = $this->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/exams/{$exam['id']}", ['reason' => 'Reemplazado por una nueva evaluacion'])
            ->assertOk();

        $response = $this->actingAs($this->admin(), 'sanctum')->get("/api/exams/{$exam['id']}/pdf");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }
}
