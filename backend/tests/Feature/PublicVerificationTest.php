<?php

namespace Tests\Feature;

use App\Models\Arl;
use App\Models\Risk;
use App\Models\User;
use App\Services\ExamGenerator;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    private function createExam(array $overrides = [])
    {
        return app(ExamGenerator::class)->create(array_merge([
            'full_name' => 'Carlos Andrés Rodríguez Pérez',
            'document_number' => '1020304050',
            'birth_date' => '1990-05-14',
            'email' => 'carlos.rodriguez@example.com',
            'phone' => '3101234567',
            'height_cm' => 175,
            'company_name' => 'Constructora Andina S.A.S.',
            'company_nit' => '830.111.222-3',
            'arl_id' => Arl::first()->id,
            'position' => 'Oficial de obra',
            'risk_ids' => Risk::limit(2)->pluck('id')->all(),
            'exam_date' => now()->toDateString(),
            'exam_type' => 'ingreso',
        ], $overrides), User::factory()->create());
    }

    public function test_public_search_by_document_confirms_the_exam_and_shows_issue_date(): void
    {
        $exam = $this->createExam();

        $this->getJson('/api/public/exams/search?document_number=1020304050')
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('results.0.order_code', $exam->order_code)
            ->assertJsonPath('results.0.result_label', 'APTO')
            ->assertJsonStructure(['message', 'issuer', 'results' => [['exam_date', 'issue_date_label']]]);
    }

    public function test_the_public_query_shows_only_the_date_of_the_document(): void
    {
        /*
         * El examen se diligencia el dia 10 pero se registra hoy. Antes la
         * consulta ensenaba las dos fechas y quien verificaba no sabia cual
         * mirar; ahora manda la que lleva impresa el documento.
         */
        $exam = $this->createExam(['exam_date' => '2026-03-10']);

        $this->assertNotSame(
            $exam->exam_date->toDateString(),
            $exam->issued_at->toDateString(),
            'El examen debía quedar registrado en una fecha distinta a la del documento.',
        );

        $entry = $this->getJson('/api/public/exams/search?document_number=1020304050')
            ->assertOk()
            ->json('results.0');

        $this->assertSame('2026-03-10', $entry['exam_date']);
        $this->assertSame('10 de marzo de 2026', $entry['issue_date_label']);

        // La etiqueta con la hora del registro ya no se publica.
        $this->assertArrayNotHasKey('issued_at_label', $entry);
    }

    public function test_the_qr_page_and_the_legend_use_the_same_date(): void
    {
        $exam = $this->createExam(['exam_date' => '2026-03-10']);

        $response = $this->getJson('/api/public/verify/'.$exam->verification_code)->assertOk();

        $this->assertSame('10 de marzo de 2026', $response->json('exam.issue_date_label'));
        $this->assertStringContainsString('10 de marzo de 2026', $response->json('legend'));
    }

    public function test_public_search_returns_not_found_for_unknown_document(): void
    {
        $this->getJson('/api/public/exams/search?document_number=7777777')
            ->assertNotFound()
            ->assertJsonPath('found', false);
    }

    public function test_public_search_requires_a_numeric_document(): void
    {
        $this->getJson('/api/public/exams/search?document_number=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('document_number');
    }

    public function test_public_search_masks_personal_data(): void
    {
        $this->createExam();

        $response = $this->getJson('/api/public/exams/search?document_number=1020304050')->assertOk();

        $this->assertSame('******4050', $response->json('results.0.document_number'));
        $this->assertSame('Carlos A. R. P.', $response->json('results.0.full_name'));
    }

    public function test_qr_verification_code_returns_the_official_legend(): void
    {
        $exam = $this->createExam();

        $response = $this->getJson('/api/public/verify/'.$exam->verification_code)->assertOk();

        $response->assertJsonPath('valid', true)
            ->assertJsonPath('exam.order_code', $exam->order_code)
            ->assertJsonStructure(['legend', 'issuer' => ['name', 'nit'], 'exam']);

        $this->assertStringContainsString($exam->order_code, $response->json('legend'));
        $this->assertStringContainsString(config('medical_center.name'), $response->json('legend'));
    }

    public function test_qr_verification_rejects_unknown_codes(): void
    {
        $this->getJson('/api/public/verify/CODIGO-FALSO-XX')
            ->assertNotFound()
            ->assertJsonPath('valid', false);
    }

    public function test_verification_url_points_to_the_frontend_platform(): void
    {
        config()->set('app.frontend_url', 'http://localhost:5173');

        $exam = $this->createExam();

        $this->assertSame(
            'http://localhost:5173/verificar/'.$exam->verification_code,
            $exam->verificationUrl(),
        );
    }

    public function test_search_returns_every_exam_of_the_worker_newest_first(): void
    {
        $this->createExam(['exam_date' => now()->subYear()->toDateString()]);
        $this->createExam(['exam_type' => 'periodico']);

        $response = $this->getJson('/api/public/exams/search?document_number=1020304050')->assertOk();

        $this->assertSame(2, $response->json('total'));
        $this->assertSame('Periódico', $response->json('results.0.exam_type_label'));
    }
}
