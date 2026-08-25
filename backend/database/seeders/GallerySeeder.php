<?php

namespace Database\Seeders;

use App\Models\GalleryImage;
use Illuminate\Database\Seeder;

/**
 * Imagenes de muestra de la galeria. Son placas generadas, no fotografias del
 * centro: estan para que las paginas publicas no se vean vacias mientras se
 * suben las reales desde Configuracion.
 *
 * Solo se cargan si la galeria esta vacia, para no resucitar lo que alguien
 * haya borrado a proposito.
 */
class GallerySeeder extends Seeder
{
    private const IMAGES = [
        ['file' => 'recepcion.jpg', 'title' => 'Recepción y admisión',
            'caption' => 'Atención y radicación de órdenes'],
        ['file' => 'consultorio.jpg', 'title' => 'Consultorio de valoración',
            'caption' => 'Examen físico y concepto de aptitud'],
        ['file' => 'visiometria.jpg', 'title' => 'Visiometría y optometría',
            'caption' => 'Agudeza visual y visión cromática'],
        ['file' => 'audiometria.jpg', 'title' => 'Audiometría',
            'caption' => 'Cabina sonoamortiguada'],
        ['file' => 'espirometria.jpg', 'title' => 'Espirometría',
            'caption' => 'Valoración de la función pulmonar'],
        ['file' => 'alturas.jpg', 'title' => 'Trabajo seguro en alturas',
            'caption' => 'Evaluación para tareas en altura'],
    ];

    public function run(): void
    {
        if (GalleryImage::query()->exists()) {
            return;
        }

        foreach (self::IMAGES as $position => $image) {
            $path = __DIR__.'/gallery/'.$image['file'];

            if (! is_readable($path)) {
                $this->command?->warn("No se encontró la imagen {$image['file']}.");

                continue;
            }

            GalleryImage::create([
                'title' => $image['title'],
                'caption' => $image['caption'],
                'image' => 'data:image/jpeg;base64,'.base64_encode(file_get_contents($path)),
                'position' => $position + 1,
                'active' => true,
            ]);
        }
    }
}
