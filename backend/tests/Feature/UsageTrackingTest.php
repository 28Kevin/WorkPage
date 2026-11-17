<?php

namespace Tests\Feature;

use App\Models\TrainingCertificate;
use App\Models\User;
use App\Support\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Eventos de uso: se acumulan fuera de la base y se vuelcan agregados. */
class UsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function developer(): User
    {
        return User::factory()->create(['role' => User::ROLE_DEVELOPER]);
    }

    private function certificate(string $code): TrainingCertificate
    {
        return TrainingCertificate::create([
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
            'verification_code' => $code,
            'issued_at' => now(),
        ]);
    }

    public function test_events_stay_in_cache_until_the_flush(): void
    {
        UsageTracker::store()->forever('usage.flushed_at', now()->timestamp);

        $this->getJson('/api/public/exams/search?document_number=123456')->assertNotFound();
        $this->certificate('C-1');
        $this->getJson('/api/public/certificates/C-1')->assertOk();

        // Las respuestas fallidas no cuentan y nada se escribio todavia.
        $this->assertSame(0, DB::table('usage_events')->count());

        UsageTracker::flush();

        $this->assertSame(1, DB::table('usage_events')->count());
        $this->assertSame(1, (int) DB::table('usage_events')->where('metric', UsageTracker::PUBLIC_VERIFY)->value('count'));
    }

    public function test_repeated_events_are_one_aggregated_row(): void
    {
        UsageTracker::store()->forever('usage.flushed_at', now()->timestamp);

        UsageTracker::record(UsageTracker::PUBLIC_SEARCH);
        UsageTracker::record(UsageTracker::PUBLIC_SEARCH);
        UsageTracker::flush();
        UsageTracker::record(UsageTracker::PUBLIC_SEARCH);
        UsageTracker::flush();

        $this->assertSame(1, DB::table('usage_events')->count());
        $this->assertSame(3, (int) DB::table('usage_events')->value('count'));
    }

    public function test_the_dashboard_reports_logins_and_the_most_consulted_documents(): void
    {
        $user = User::factory()->create(['password' => 'secreto-largo']);
        $certificate = $this->certificate('C-42');

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'secreto-largo'])->assertOk();
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'otra-clave'])->assertUnprocessable();
        $this->getJson('/api/public/certificates/C-42')->assertOk();
        $this->getJson('/api/public/certificates/C-42')->assertOk();

        $activity = $this->actingAs($this->developer())
            ->getJson('/api/dev/usage?fresh=1')
            ->assertOk()
            ->json('activity');

        $this->assertSame(1, $activity['totals'][UsageTracker::LOGIN]);
        $this->assertSame(1, $activity['totals'][UsageTracker::LOGIN_FAILED]);
        $this->assertSame(2, $activity['totals'][UsageTracker::PUBLIC_VERIFY]);
        $this->assertSame($certificate->order_code, $activity['top_documents'][0]['order_code']);
        $this->assertSame(2, $activity['top_documents'][0]['count']);
        $this->assertSame($user->name, $activity['top_users'][0]['name']);
    }

    public function test_the_report_is_reused_from_cache(): void
    {
        $developer = $this->developer();

        $first = $this->actingAs($developer)->getJson('/api/dev/usage')->json('generated_at');
        $this->travel(5)->minutes();
        $second = $this->actingAs($developer)->getJson('/api/dev/usage')->json('generated_at');

        $this->assertSame($first, $second);
    }
}
