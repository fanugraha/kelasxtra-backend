<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;

class ProgramController extends Controller
{
    /**
     * GET /api/programs
     * Daftar program aktif untuk filter chip di Beranda (CPNS, TOEFL, dst).
     */
    public function index()
    {
        return Program::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon']);
    }
}
