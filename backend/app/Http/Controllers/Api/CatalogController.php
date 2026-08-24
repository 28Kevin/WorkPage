<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExamType;
use App\Http\Controllers\Controller;
use App\Models\Arl;
use App\Models\City;
use App\Models\Eps;
use App\Models\Risk;
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
            'cities' => City::where('active', true)->orderBy('name')
                ->get(['id', 'name', 'department']),
            'risks' => Risk::where('active', true)->orderBy('name')
                ->get(['id', 'name', 'slug', 'description']),
            'exam_types' => ExamType::options(),
        ]);
    }
}
