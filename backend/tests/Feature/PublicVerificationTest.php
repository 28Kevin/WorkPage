<?php

namespace Tests\Feature;

use App\Models\Arl;
use App\Models\City;
use App\Models\Eps;
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
            'eps_id' => Eps::first()->id,
            'arl_id' => Arl::first()->id,
            'city_id' => City::first()->id,
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
            ->assertJsonPath('results.0.issued_at', $exam->issued_at->toIso8601String())
            ->assertJsonStructure(['message', 'issuer', 'results' => [['exam_date', 'issued_at', 'issued_at_label']]]);
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
        $this->assertSame('Periódica', $response->json('results.0.exam_type_label'));
    }
}
