<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ExamBatch;
use Illuminate\Http\Request;
class ExamBatchController extends Controller
{
    /**
     * GET /api/exam-batches
     * Daftar batch try out yang leaderboard-nya sudah siap (status "ranked"),
     * untuk dropdown "Pilih Tryout" di Leaderboard Beranda.
     */
    public function index(Request $request)
    {
        return ExamBatch::with('exam.bank')
            ->where('status', 'ranked')
            ->latest('end_at')
            ->take(20)
            ->get(['id', 'exam_id', 'name', 'end_at', 'is_national'])
            ->map(fn (ExamBatch $batch) => [
                'id' => $batch->id,
                'exam' => $batch->exam ? ['id' => $batch->exam->id, 'title' => $batch->exam->title] : null,
                'name' => $batch->name,
                'end_at' => $batch->end_at,
                'is_national' => $batch->is_national,
                'program_id' => $batch->exam?->bank?->program_id,
            ]);
    }
}
