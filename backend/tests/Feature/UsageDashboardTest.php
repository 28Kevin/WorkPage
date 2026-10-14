<?php

namespace Tests\Feature;

use App\Models\BillingAccount;
use App\Models\FoodHandlerRecord;
use App\Models\TrainingCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Tablero de uso: acceso restringido y cifras por periodo y por hora. */
class UsageDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function developer(): User
    {
        return User::factory()->create(['role' => User::ROLE_DEVELOPER]);
    }

    /** Documentos de prueba creados en un instante concreto. */
    private function certificateAt(string $moment): void
    {
        Carbon::setTestNow($moment);

        TrainingCertificate::create([
            'order_number' => random_int(1, 999999),
            'order_code' => 'S.S-'.random_int(1, 999999),
            'participant_name' => 'Participante de prueba',
            'document_number' => '1082370757',
            'course' => 'Aparejador',
            'city' => 'Bogotá',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-01',
            'hours' => 8,
            'signed_date' => '2026-01-01',
            'verification_code' => 'C-'.random_int(1, 999999999),
            'issued_at' => now(),
        ]);

        Carbon::setTestNow();
    }

    private function foodHandlerAt(string $moment): void
    {
        Carbon::setTestNow($moment);

        FoodHandlerRecord::create([
            'order_number' => random_int(1, 999999),
            'order_code' => 'MA-'.random_int(1, 999999),
            'issue_date' => '2026-01-01',
            'full_name' => 'Paciente de prueba',
            'document_number' => '1020304050',
            'age' => 30,
            'verification_code' => 'F-'.random_int(1, 999999999),
            'issued_at' => now(),
        ]);

        Carbon::setTestNow();
    }

    private function billingAt(string $moment): void
    {
        Carbon::setTestNow($moment);

        BillingAccount::create([
            'number' => random_int(1, 999999),
            'number_code' => str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'issue_place' => 'Bogotá',
            'issue_date' => '2026-01-01',
            'company_name' => 'Empresa de prueba',
            'company_nit' => '900000000-1',
            'company_address' => 'CL 1',
            'company_city' => 'Bogotá',
            'items' => [['concept' => 'A', 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100]],
            'total' => 100,
        ]);

        Carbon::setTestNow();
    }

    public function test_the_dashboard_needs_a_session(): void
    {
        $this->getJson('/api/dev/usage')->assertUnauthorized();
    }

    public function test_an_administrator_cannot_reach_the_dashboard(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/dev/usage')
            ->assertForbidden();
    }

    public function test_a_developer_account_reaches_the_dashboard(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->getJson('/api/dev/usage')
            ->assertOk()
            ->assertJsonStructure([
                'range' => ['from', 'to', 'period'],
                'documents' => [['key', 'label']],
                'totals' => ['medical_exam', 'food_handler', 'training_certificate', 'billing_account', 'all'],
                'series' => [['bucket', 'label', 'total']],
                'by_hour' => [['hour', 'label', 'count']],
                'by_weekday' => [['weekday', 'label', 'count']],
            ]);
    }

    public function test_the_session_says_whether_the_account_is_a_developer(): void
    {
        $this->actingAs($this->developer(), 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_DEVELOPER)
            ->assertJsonPath('user.is_developer', true);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.is_developer', false);
    }

    public function test_an_account_is_an_administrator_unless_it_says_otherwise(): void
    {
        // Las cuentas que ya existian no deben heredar acceso de desarrollo.
        $user = User::factory()->create();

        $this->assertSame(User::ROLE_ADMIN, $user->fresh()->role);
        $this->assertFalse($user->isDeveloper());
    }

    public function test_it_counts_each_kind_of_document(): void
    {
        $this->certificateAt('2026-10-10 09:00:00');
        $this->certificateAt('2026-10-10 11:00:00');
        $this->foodHandlerAt('2026-10-11 09:30:00');
        $this->billingAt('2026-10-12 15:00:00');

        $totals = $this->actingAs($this->developer(), 'sanctum')
            ->getJson('/api/dev/usage?period=day&from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->json('totals');

        $this->assertSame(2, $totals['training_certificate']);
        $this->assertSame(1, $totals['food_handler']);
        $this->assertSame(1, $totals['billing_account']);
        $this->assertSame(0, $totals['medical_exam']);
        $this->assertSame(4, $totals['all']);
    }

    public function test_the_series_keeps_the_empty_intervals(): void
    {
        $this->certificateAt('2026-10-10 09:00:00');
        $this->certificateAt('2026-10-12 09:00:00');

        $series = collect(
            $this->actingAs($this->developer(), 'sanctum')
                ->getJson('/api/dev/usage?period=day&from=2026-10-10&to=2026-10-12')
                ->assertOk()
                ->json('series'),
        );

        // Tres días, incluido el 11 sin movimiento: una gráfica con huecos
        // daría a entender un ritmo de uso que no fue.
        $this->assertCount(3, $series);
        $this->assertSame([1, 0, 1], $series->pluck('total')->all());
    }

    public function test_it_reports_the_busiest_hour_of_the_day(): void
    {
        $this->certificateAt('2026-10-10 14:10:00');
        $this->foodHandlerAt('2026-10-11 14:40:00');
        $this->billingAt('2026-10-12 09:00:00');

        $response = $this->actingAs($this->developer(), 'sanctum')
            ->getJson('/api/dev/usage?period=day&from=2026-10-01&to=2026-10-31')
            ->assertOk();

        $byHour = collect($response->json('by_hour'))->keyBy('hour');

        $this->assertCount(24, $byHour);
        $this->assertSame(2, $byHour[14]['count']);
        $this->assertSame(1, $byHour[9]['count']);
        $this->assertSame(0, $byHour[3]['count']);

        $this->assertSame(14, $response->json('busiest_hour.hour'));
        $this->assertSame('14:00', $response->json('busiest_hour.label'));
    }

    public function test_without_any_document_it_does_not_invent_a_busiest_hour(): void
    {
        // Sin registros, "la hora pico es la medianoche" seria una afirmacion
        // falsa sacada de un reparto de ceros.
        $this->actingAs($this->developer(), 'sanctum')
            ->getJson('/api/dev/usage?period=day&from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('busiest_hour', null)
            ->assertJsonPath('totals.all', 0);
    }

    public function test_it_groups_by_weekday(): void
    {
        // 2026-10-12 es lunes.
        $this->certificateAt('2026-10-12 10:00:00');
        $this->certificateAt('2026-10-13 10:00:00');

        $byWeekday = collect(
            $this->actingAs($this->developer(), 'sanctum')
                ->getJson('/api/dev/usage?period=day&from=2026-10-01&to=2026-10-31')
                ->json('by_weekday'),
        )->keyBy('label');

        $this->assertSame(1, $byWeekday['Lunes']['count']);
        $this->assertSame(1, $byWeekday['Martes']['count']);
        $this->assertSame(0, $byWeekday['Domingo']['count']);
    }

    public function test_every_grouping_is_accepted_and_an_invented_one_is_not(): void
    {
        foreach (['hour', 'day', 'week', 'month'] as $period) {
            $this->actingAs($this->developer(), 'sanctum')
                ->getJson("/api/dev/usage?period={$period}")
                ->assertOk()
                ->assertJsonPath('range.period', $period);
        }

        $this->actingAs($this->developer(), 'sanctum')
            ->getJson('/api/dev/usage?period=siglo')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_hourly_grouping_splits_the_day(): void
    {
        $this->certificateAt('2026-10-10 09:15:00');
        $this->certificateAt('2026-10-10 09:45:00');
        $this->foodHandlerAt('2026-10-10 11:00:00');

        $series = collect(
            $this->actingAs($this->developer(), 'sanctum')
                ->getJson('/api/dev/usage?period=hour&from=2026-10-10&to=2026-10-10')
                ->json('series'),
        )->keyBy('label');

        $this->assertSame(2, $series['10/10 09:00']['total']);
        $this->assertSame(1, $series['10/10 11:00']['total']);
        $this->assertSame(0, $series['10/10 10:00']['total']);
    }
}
