<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tutor;

class TutorController extends Controller
{
    public function index()
    {
        $tutors = Tutor::with('user:id,name')->get();

        return $tutors->map(fn ($tutor) => [
            'id' => $tutor->id,
            'name' => $tutor->user->name ?? 'Tutor',
            'bio' => $tutor->bio,
            'expertise' => $tutor->expertise,
            'photo' => null,
        ]);
    }
}
