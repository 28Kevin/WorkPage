<?php

namespace Tests\Feature;

use App\Models\GalleryImage;
use Database\Seeders\GallerySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Imagenes de muestra que acompañan a la instalacion. */
class GallerySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_the_sample_images(): void
    {
        $this->seed(GallerySeeder::class);

        $images = GalleryImage::visible()->get();

        $this->assertCount(6, $images);
        $this->assertSame('Recepción y admisión', $images->first()->title);

        // Se guardan como data URI, listas para servirse sin pasar por disco.
        $images->each(fn (GalleryImage $image) => $this->assertStringStartsWith(
            'data:image/jpeg;base64,',
            $image->image,
        ));
    }

    public function test_it_does_not_resurrect_deleted_images(): void
    {
        $this->seed(GallerySeeder::class);

        GalleryImage::query()->where('title', '!=', 'Audiometría')->delete();

        $this->seed(GallerySeeder::class);

        $this->assertSame(1, GalleryImage::count());
        $this->assertSame('Audiometría', GalleryImage::first()->title);
    }

    public function test_the_sample_images_reach_the_public_gallery(): void
    {
        $this->seed(GallerySeeder::class);

        $this->getJson('/api/gallery')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.title', 'Recepción y admisión');
    }
}
