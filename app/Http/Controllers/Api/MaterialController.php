<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Services\AccessControlService;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function __construct(protected AccessControlService $accessControl)
    {
    }

    /**
     * GET /api/materials/{material}
     */
    public function show(Request $request, Material $material)
    {
        if (! $this->accessControl->canAccessMaterial($request->user(), $material)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke materi ini.'], 403);
        }

        return response()->json($material);
    }
}
