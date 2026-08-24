<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Formulario publico de contacto y su bandeja en el panel. */
class ContactMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El limitador vive en cache y el driver array se comparte entre pruebas.
        Cache::flush();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Marcela Ospina',
            'email' => 'marcela.ospina@example.com',
            'phone' => '3125550011',
            'subject' => 'Cotización de exámenes para 12 operarios',
            'message' => 'Necesito cotizar exámenes de ingreso para trabajo en alturas de 12 operarios.',
        ], $overrides);
    }

    public function test_anyone_can_send_a_message_without_a_session(): void
    {
        $this->postJson('/api/public/contact', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Gracias por escribirnos. Le responderemos al correo indicado.');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'marcela.ospina@example.com',
            'handled_at' => null,
        ]);
    }

    public function test_the_message_is_validated(): void
    {
        $this->postJson('/api/public/contact', [
            'name' => 'A',
            'email' => 'no-es-un-correo',
            'subject' => '',
            'message' => 'corto',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
    }

    public function test_the_inbox_requires_a_session(): void
    {
        $this->getJson('/api/contact-messages')->assertUnauthorized();
    }

    public function test_the_inbox_lists_newest_first_and_filters_by_status(): void
    {
        $this->postJson('/api/public/contact', $this->payload(['subject' => 'Primero']))->assertCreated();
        $this->postJson('/api/public/contact', $this->payload(['subject' => 'Segundo']))->assertCreated();

        $admin = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/contact-messages')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.subject', 'Segundo')
            ->assertJsonPath('data.0.handled', false);

        $first = ContactMessage::where('subject', 'Primero')->first();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/contact-messages/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.handled', true)
            ->assertJsonPath('data.handled_by', $admin->name);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/contact-messages?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Segundo');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/contact-messages?status=handled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Primero');
    }

    public function test_marking_as_handled_can_be_undone(): void
    {
        $this->postJson('/api/public/contact', $this->payload())->assertCreated();

        $admin = User::factory()->create();
        $message = ContactMessage::first();

        $this->actingAs($admin, 'sanctum')->patchJson("/api/contact-messages/{$message->id}")->assertOk();
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/contact-messages/{$message->id}")
            ->assertOk()
            ->assertJsonPath('data.handled', false);

        $this->assertNull(ContactMessage::first()->handled_at);
    }

    public function test_a_message_can_be_deleted_from_the_inbox(): void
    {
        $this->postJson('/api/public/contact', $this->payload())->assertCreated();

        $message = ContactMessage::first();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/contact-messages/{$message->id}")
            ->assertOk();

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_the_public_form_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/public/contact', $this->payload(['subject' => "Consulta {$i}"]))
                ->assertCreated();
        }

        $this->postJson('/api/public/contact', $this->payload(['subject' => 'Una mas']))
            ->assertStatus(429);
    }

    public function test_the_schedule_is_part_of_the_editable_branding(): void
    {
        $this->getJson('/api/branding')
            ->assertOk()
            ->assertJsonPath('branding.center.schedule', fn ($value) => is_string($value) && $value !== '');
    }
}
