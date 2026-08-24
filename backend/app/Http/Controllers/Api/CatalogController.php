<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Enums\ExamResult;
use App\Enums\ExamType;
use App\Enums\Sex;
use App\Http\Controllers\Controller;
use App\Models\Afp;
use App\Models\Arl;
use App\Models\City;
use App\Models\Eps;
use App\Models\Risk;
use App\Support\ExamForm;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'eps' => Eps::where('active', true)->orderBy('name')
                ->get(['id', 'name', 'code']),
            'arls' => Arl::where('active', true)->orderBy('name')
                ->get(['id', 'name', 'certificate_url']),
            'afps' => Afp::where('active', true)->orderBy('name')
                ->get(['id', 'name']),
            'cities' => City::where('active', true)->orderBy('name')
                ->get(['id', 'name', 'department']),
            'risks' => Risk::where('active', true)->orderBy('name')
                ->get(['id', 'name', 'slug', 'description']),

            'exam_types' => ExamType::options(),
            'document_types' => DocumentType::options(),
            'sexes' => Sex::options(),
            'aptitude_results' => ExamResult::options(),

            // Bloques semiestructurados del formato: el formulario los pinta
            // a partir de esto en vez de repetir las claves en el frontend.
            'form' => ExamForm::definitions(),
        ]);
    }
}
