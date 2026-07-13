<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint khusus tutor untuk menilai soal essay (section 3 & 7 desain MVP).
 */
class TutorGradingController extends Controller
{
    /**
     * GET /api/tutor/essay-queue
     * Daftar jawaban essay yang masih nunggu dinilai.
     */
    public function index(Request $request)
    {
        $answers = ExamAnswer::where('needs_manual_grading', true)
            ->with(['question', 'attempt.user:id,name'])
            ->latest()
            ->paginate(20);

        return response()->json($answers);
    }

    /**
     * POST /api/tutor/essay-answers/{answer}/grade
     * Body: is_correct (bool)
     */
    public function grade(Request $request, ExamAnswer $answer)
    {
        $data = $request->validate([
            'is_correct' => ['required', 'boolean'],
        ]);

        abort_unless($answer->needs_manual_grading, 422, 'Jawaban ini bukan essay / sudah dinilai.');

        DB::transaction(function () use ($answer, $data) {
            $answer->update([
                'is_correct' => $data['is_correct'],
                'needs_manual_grading' => false,
            ]);

            // Memanggil sentralisasi kalkulasi skor baru yang aman dari dualisme data
            $answer->attempt->recalculateScore();
        });

        return response()->json([
            'message' => 'Jawaban essay berhasil dinilai.',
            'data' => $answer->attempt->fresh()
        ]);
    }
}