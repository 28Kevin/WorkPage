<?php

namespace Tests\Feature;

use App\Models\GalleryImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Galeria de fotografias del centro medico. */
class GalleryImageTest extends TestCase
{
    use RefreshDatabase;

    /** PNG de 1x1 valido, suficiente para probar el flujo. */
    private const PIXEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Sala de valoración médica',
            'caption' => 'Consultorio equipado para evaluaciones ocupacionales',
            'image' => self::PIXEL,
        ], $overrides);
    }

    public function test_the_public_gallery_only_shows_active_images_in_order(): void
    {
        GalleryImage::create($this->payload(['title' => 'Segunda', 'position' => 2]));
        GalleryImage::create($this->payload(['title' => 'Primera', 'position' => 1]));
        GalleryImage::create($this->payload(['title' => 'Oculta', 'position' => 3, 'active' => false]));

        $this->getJson('/api/gallery')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Primera')
            ->assertJsonPath('data.1.title', 'Segunda');
    }

    public function test_uploading_requires_a_session(): void
    {
        $this->postJson('/api/gallery', $this->payload())->assertUnauthorized();

        $this->assertDatabaseCount('gallery_images', 0);
    }

    public function test_an_admin_uploads_an_image_and_it_goes_last(): void
    {
        GalleryImage::create($this->payload(['title' => 'Existente', 'position' => 4]));

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/gallery', $this->payload(['title' => 'Nueva']))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Nueva')
            ->assertJsonPath('data.position', 5)
            ->assertJsonPath('data.active', true);
    }

    public function test_only_real_images_are_accepted(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/gallery', $this->payload(['image' => 'https://ejemplo.test/foto.jpg']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_an_image_can_be_hidden_renamed_and_reordered(): void
    {
        $image = GalleryImage::create($this->payload());

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->patchJson("/api/gallery/{$image->id}", [
                'title' => 'Sala de espera',
                'caption' => null,
                'position' => 9,
                'active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Sala de espera')
            ->assertJsonPath('data.active', false);

        // Al ocultarla desaparece de la galería pública.
        $this->getJson('/api/gallery')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_the_panel_lists_hidden_images_too(): void
    {
        GalleryImage::create($this->payload(['title' => 'Oculta', 'active' => false]));

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/gallery/all')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Oculta');
    }

    public function test_an_image_can_be_deleted(): void
    {
        $image = GalleryImage::create($this->payload());

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/gallery/{$image->id}")
            ->assertOk();

        $this->assertDatabaseCount('gallery_images', 0);
    }
}
