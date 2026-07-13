<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Services\AccessControlService;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function __construct(protected AccessControlService $accessControl)
    {
    }

    /**
     * GET /api/classes
     */
    public function index(Request $request)
    {
        $classes = ClassRoom::with('package')->where('status', 'active')->get();
        $user = $request->user();

        return $classes->map(fn (ClassRoom $class) => [
            'id' => $class->id,
            'name' => $class->name,
            'status' => $class->status,
            'program_id' => $class->package?->program_id,
            'is_accessible' => $user ? $this->accessControl->canAccessClass($user, $class) : false,
        ]);
    }

    /**
     * GET /api/classes/{class}
     */
    public function show(Request $request, ClassRoom $class)
    {
        if (! $this->accessControl->canAccessClass($request->user(), $class)) {
            return response()->json(['message' => 'Anda belum terdaftar aktif di kelas ini.'], 403);
        }

        return response()->json($class->load(['materials', 'schedules', 'tutor.user']));
    }
}
