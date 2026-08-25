<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGalleryImageRequest;
use App\Http\Resources\GalleryImageResource;
use App\Models\GalleryImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class GalleryImageController extends Controller
{
    /** Publico: alimenta la galeria de las paginas de servicios y contacto. */
    public function index(): AnonymousResourceCollection
    {
        return GalleryImageResource::collection(GalleryImage::visible()->get());
    }

    /** Panel: incluye tambien las ocultas, para poder reactivarlas. */
    public function all(): AnonymousResourceCollection
    {
        return GalleryImageResource::collection(
            GalleryImage::orderBy('position')->orderBy('id')->get(),
        );
    }

    public function store(StoreGalleryImageRequest $request): JsonResponse
    {
        $image = GalleryImage::create([
            ...$request->validated(),
            // Sin posicion explicita la nueva imagen va al final de la galeria.
            'position' => $request->integer('position', GalleryImage::max('position') + 1),
            'active' => $request->boolean('active', true),
        ]);

        return GalleryImageResource::make($image)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(StoreGalleryImageRequest $request, GalleryImage $image): GalleryImageResource
    {
        $image->update($request->validated());

        return GalleryImageResource::make($image);
    }

    public function destroy(GalleryImage $image): JsonResponse
    {
        $image->delete();

        return response()->json(['message' => 'Imagen eliminada de la galería.']);
    }
}
