<?php

namespace Tests\Feature;

use App\Models\Arl;
use App\Models\FoodHandlerRecord;
use App\Models\User;
use App\Services\ExamGenerator;
use App\Services\FoodHandlerRecordGenerator;
use App\Support\FoodHandlerForm;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Paquete de manipulacion de alimentos: serial propio, verificacion publica y
 * precarga de los valores normales de laboratorio.
 */
class FoodHandlerRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    private function createRecord(array $overrides = []): FoodHandlerRecord
    {
        return app(FoodHandlerRecordGenerator::class)->create(array_replace([
            'issue_date' => now()->toDateString(),
            'full_name' => 'Luz Marina Quintero Ávila',
            'document_number' => '1020304050',
            'age' => 34,
        ], $overrides), User::factory()->create());
    }

    /** Un examen ocupacional de la misma persona, para contrastar los dos tipos. */
    private function createExam(array $overrides = [], ?User $user = null)
    {
        return app(ExamGenerator::class)->create(array_replace([
            'full_name' => 'Luz Marina Quintero Ávila',
            'document_number' => '1020304050',
            'birth_date' => '1992-03-11',
            'sex' => 'F',
            'height_cm' => 165,
            'arl_id' => Arl::first()->id,
            'is_independent' => false,
            'company_name' => 'Restaurante La Fonda S.A.S.',
            'company_nit' => '900.111.222-3',
            'position' => 'Auxiliar de cocina',
            'exam_date' => now()->toDateString(),
            'exam_type' => 'ingreso',
            'aptitude_position' => 'APTO',
            'aptitude_heights' => 'APTO',
            'aptitude_confined' => 'APTO',
            'consent_accepted' => true,
        ], $overrides), $user ?? User::factory()->create());
    }

    public function test_the_serial_marks_the_document_type_and_counts_apart_from_the_exam(): void
    {
        $first = $this->createRecord();
        $second = $this->createRecord(['document_number' => '1020304051']);

        $this->assertSame(1, (int) $first->order_number);
        $this->assertSame(2, (int) $second->order_number);
        $this->assertSame('MA-'.date('Y').'-000001', $first->order_code);
        $this->assertSame('MA-'.date('Y').'-000002', $second->order_code);

        // El examen ocupacional conserva su propio contador y su prefijo.
        $this->assertSame('EMO-'.date('Y').'-000001', $this->createExam()->order_code);
    }

    public function test_lab_results_arrive_preloaded_with_the_normal_value_of_each_exam(): void
    {
        $record = $this->createRecord();

        $this->assertSame('No reactivo', $record->lab_results['vdrl']['resultado']['resultado']);
        $this->assertSame('Negativo', $record->lab_results['uroanalisis']['quimico']['proteinas']);
        $this->assertSame('Pastoso', $record->lab_results['coprologico']['macroscopico']['aspecto']);

        // El documento se emite con todos los campos del formato presentes.
        $this->assertSame(FoodHandlerForm::normalResults(), $record->lab_results);
    }

    public function test_a_real_result_replaces_the_preloaded_value(): void
    {
        $record = $this->createRecord([
            'lab_results' => ['vdrl' => ['resultado' => ['resultado' => 'Reactivo']]],
        ]);

        $this->assertSame('Reactivo', $record->lab_results['vdrl']['resultado']['resultado']);
        // Lo que no se envio conserva su valor normal.
        $this->assertSame('Negativo', $record->lab_results['uroanalisis']['quimico']['proteinas']);
    }

    public function test_corrections_keep_the_serial_the_code_and_the_issue_date(): void
    {
        $record = $this->createRecord();

        $corrected = app(FoodHandlerRecordGenerator::class)->update($record, [
            'issue_date' => $record->issue_date->toDateString(),
            'full_name' => 'Luz Marina Quintero Ávila',
            'document_number' => '1020304050',
            'age' => 35,
            'lab_results' => ['koh' => ['resultado' => ['resultado' => 'Se observan hifas']]],
        ]);

        $this->assertSame($record->order_code, $corrected->order_code);
        $this->assertSame($record->verification_code, $corrected->verification_code);
        $this->assertSame($record->issued_at->toIso8601String(), $corrected->issued_at->toIso8601String());

        $this->assertSame(35, $corrected->age);
        $this->assertSame('Se observan hifas', $corrected->lab_results['koh']['resultado']['resultado']);
    }

    public function test_the_qr_verifies_the_new_document_and_says_which_type_it_is(): void
    {
        $record = $this->createRecord();

        $this->getJson('/api/public/verify/'.$record->verification_code)
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('document_type', FoodHandlerRecord::TYPE)
            ->assertJsonPath('document_type_label', FoodHandlerRecord::TYPE_LABEL)
            ->assertJsonPath('exam.order_code', $record->order_code);
    }

    public function test_an_annulled_record_still_answers_the_printed_qr(): void
    {
        $record = $this->createRecord();

        $record->update(['annulled_at' => now(), 'annulment_reason' => 'Emitido por error']);

        $this->getJson('/api/public/verify/'.$record->verification_code)
            ->assertStatus(410)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('annulled', true)
            ->assertJsonPath('document_type', FoodHandlerRecord::TYPE);
    }

    public function test_the_public_search_finds_both_documents_and_labels_each_one(): void
    {
        $this->createExam();
        $this->createRecord();

        $response = $this->getJson('/api/public/exams/search?document_number=1020304050')->assertOk();

        $this->assertSame(2, $response->json('total'));

        $types = collect($response->json('results'))->pluck('document_type')->sort()->values()->all();
        $this->assertSame([FoodHandlerRecord::TYPE, 'medical_exam'], $types);
    }

    public function test_an_annulled_record_leaves_the_public_search(): void
    {
        $record = $this->createRecord();

        $record->update(['annulled_at' => now(), 'annulment_reason' => 'Documento reemplazado']);

        $this->getJson('/api/public/exams/search?document_number=1020304050')
            ->assertNotFound()
            ->assertJsonPath('found', false);
    }

    public function test_a_verification_code_is_never_shared_between_the_two_documents(): void
    {
        $record = $this->createRecord();

        // El usuario se crea antes del doble: su factory tambien pide una
        // cadena aleatoria y descuadraria la cola.
        $user = User::factory()->create();

        // Se fuerza al generador a proponer primero el codigo que ya usa el
        // paquete de alimentos: debe descartarlo en vez de duplicarlo, porque
        // una sola pagina publica resuelve los dos tipos.
        $queue = [...explode('-', $record->verification_code), 'ZZZZZZ', 'YYYYYY', 'XXXXXX'];

        // Por referencia: con fn() la cola se copiaria y daria siempre lo mismo.
        Str::createRandomStringsUsing(function () use (&$queue) {
            return array_shift($queue) ?? 'AAAAAA';
        });

        try {
            $exam = $this->createExam([], $user);
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertNotSame($record->verification_code, $exam->verification_code);
        $this->assertSame('ZZZZZZ-YYYYYY-XXXXXX', $exam->verification_code);
    }
}
