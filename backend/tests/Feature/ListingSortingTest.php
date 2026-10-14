<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\TrainingCertificateController;
use App\Models\TrainingCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ordenamiento y tamanio de pagina de los listados del panel. Se prueban sobre
 * los certificados, que comparten el mismo trait con los demas listados.
 */
class ListingSortingTest extends TestCase
{
    use RefreshDatabase;

    /** Contador por prueba: un static se conservaría entre pruebas. */
    private int $sequence = 0;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function certificate(array $overrides = []): TrainingCertificate
    {
        $n = ++$this->sequence;

        return TrainingCertificate::create(array_replace([
            'order_number' => $n,
            'order_code' => 'S.S-'.$n,
            'participant_name' => 'Participante '.$n,
            'document_number' => (string) (1000000 + $n),
            'course' => 'Curso '.$n,
            'city' => 'Bogotá',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-0'.min($n, 9),
            'hours' => $n * 2,
            'signed_date' => '2026-01-10',
            'verification_code' => 'V-'.$n,
            'issued_at' => now(),
        ], $overrides));
    }

    /** @return array<int, string> */
    private function codes(string $query): array
    {
        return collect(
            $this->actingAs($this->admin(), 'sanctum')
                ->getJson('/api/certificates'.$query)
                ->assertOk()
                ->json('data'),
        )->pluck('order_code')->all();
    }

    public function test_by_default_the_newest_consecutive_comes_first(): void
    {
        $this->certificate();
        $this->certificate();
        $this->certificate();

        $this->assertSame(['S.S-3', 'S.S-2', 'S.S-1'], $this->codes(''));
    }

    public function test_it_sorts_ascending_and_descending(): void
    {
        $this->certificate(['participant_name' => 'Carlos']);
        $this->certificate(['participant_name' => 'Ana']);
        $this->certificate(['participant_name' => 'Beatriz']);

        $names = fn (string $direction) => collect(
            $this->actingAs($this->admin(), 'sanctum')
                ->getJson("/api/certificates?sort=participant_name&direction={$direction}")
                ->json('data'),
        )->pluck('participant_name')->all();

        $this->assertSame(['Ana', 'Beatriz', 'Carlos'], $names('asc'));
        $this->assertSame(['Carlos', 'Beatriz', 'Ana'], $names('desc'));
    }

    public function test_it_sorts_by_a_numeric_field(): void
    {
        $this->certificate(['hours' => 40]);
        $this->certificate(['hours' => 8]);
        $this->certificate(['hours' => 16]);

        $hours = collect(
            $this->actingAs($this->admin(), 'sanctum')
                ->getJson('/api/certificates?sort=hours&direction=asc')
                ->json('data'),
        )->pluck('hours')->all();

        $this->assertSame([8, 16, 40], $hours);
    }

    public function test_the_consecutive_sorts_by_number_and_not_as_text(): void
    {
        // Como texto, "S.S-10" iria antes que "S.S-9".
        foreach (range(1, 12) as $i) {
            $this->certificate();
        }

        $codes = $this->codes('?sort=order_code&direction=desc&per_page=10');

        $this->assertSame('S.S-12', $codes[0]);
        $this->assertSame('S.S-11', $codes[1]);
        $this->assertSame('S.S-10', $codes[2]);
    }

    public function test_an_invented_sort_field_falls_back_to_the_default(): void
    {
        $this->certificate();
        $this->certificate();

        /*
         * No revienta ni filtra el nombre a la consulta: cae en el campo por
         * defecto (el consecutivo). La dirección pedida sí se respeta, porque
         * 'asc' es válida por su cuenta.
         */
        $this->assertSame(['S.S-1', 'S.S-2'], $this->codes('?sort=DROP TABLE&direction=asc'));
        $this->assertSame(['S.S-2', 'S.S-1'], $this->codes('?sort=DROP TABLE&direction=desc'));
    }

    public function test_an_invented_direction_is_treated_as_descending(): void
    {
        $this->certificate();
        $this->certificate();

        $this->assertSame(['S.S-2', 'S.S-1'], $this->codes('?sort=order_code&direction=lateral'));
    }

    public function test_the_page_size_is_respected(): void
    {
        foreach (range(1, 12) as $i) {
            $this->certificate();
        }

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/certificates?per_page=10')
            ->assertOk();

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(10, $response->json('meta.per_page'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }

    public function test_a_page_size_outside_the_offered_options_falls_back(): void
    {
        $this->certificate();

        // Evita que alguien pida diez mil filas de una.
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/certificates?per_page=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', TrainingCertificateController::DEFAULT_PAGE_SIZE);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/certificates?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.per_page', TrainingCertificateController::DEFAULT_PAGE_SIZE);
    }

    public function test_tied_rows_do_not_repeat_or_vanish_between_pages(): void
    {
        /*
         * Seis certificados con la misma fecha: al ordenar por ella el motor
         * no garantiza el orden de los empatados, y sin un desempate estable
         * la segunda pagina puede repetir filas de la primera.
         */
        foreach (range(1, 20) as $i) {
            $this->certificate(['end_date' => '2026-05-05']);
        }

        $page = fn (int $n) => collect(
            $this->actingAs($this->admin(), 'sanctum')
                ->getJson("/api/certificates?sort=end_date&direction=desc&per_page=10&page={$n}")
                ->json('data'),
        )->pluck('order_code')->all();

        $first = $page(1);
        $second = $page(2);

        $this->assertCount(10, $first);
        $this->assertCount(10, $second);
        $this->assertEmpty(array_intersect($first, $second), 'Una fila salió en las dos páginas.');
        $this->assertCount(20, array_unique([...$first, ...$second]));
    }

    public function test_sorting_survives_the_search_filter(): void
    {
        $this->certificate(['participant_name' => 'Ana Torres']);
        $this->certificate(['participant_name' => 'Ana Beltrán']);
        $this->certificate(['participant_name' => 'Carlos Ruiz']);

        $names = collect(
            $this->actingAs($this->admin(), 'sanctum')
                ->getJson('/api/certificates?search=Ana&sort=participant_name&direction=asc')
                ->assertOk()
                ->json('data'),
        )->pluck('participant_name')->all();

        $this->assertSame(['Ana Beltrán', 'Ana Torres'], $names);
    }

    public function test_every_listing_accepts_sorting_and_page_size(): void
    {
        $admin = $this->admin();

        foreach (['/api/exams', '/api/food-handler', '/api/certificates', '/api/billing'] as $endpoint) {
            $this->actingAs($admin, 'sanctum')
                ->getJson("{$endpoint}?sort=inexistente&direction=asc&per_page=25")
                ->assertOk()
                ->assertJsonPath('meta.per_page', 25);
        }
    }
}
