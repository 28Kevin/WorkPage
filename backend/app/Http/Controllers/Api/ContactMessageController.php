<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactMessageRequest;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ContactMessageController extends Controller
{
    /** Formulario publico de contacto. */
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        ContactMessage::create($request->validated());

        return response()->json([
            'message' => 'Gracias por escribirnos. Le responderemos al correo indicado.',
        ], Response::HTTP_CREATED);
    }

    /** Bandeja del modulo administrativo. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $messages = ContactMessage::query()
            ->with('handler')
            ->when($request->input('status') === 'pending', fn ($q) => $q->pending())
            ->when($request->input('status') === 'handled', fn ($q) => $q->handled())
            // Se desempata por id: dos mensajes pueden caer en el mismo segundo.
            ->latest()
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return ContactMessageResource::collection($messages);
    }

    /** Marca o desmarca un mensaje como atendido. */
    public function toggle(Request $request, ContactMessage $message): ContactMessageResource
    {
        $handled = ! $message->isHandled();

        $message->update([
            'handled_at' => $handled ? now() : null,
            'handled_by' => $handled ? $request->user()?->id : null,
        ]);

        return ContactMessageResource::make($message->load('handler'));
    }

    public function destroy(ContactMessage $message): JsonResponse
    {
        $message->delete();

        return response()->json(['message' => 'Mensaje eliminado.']);
    }
}
