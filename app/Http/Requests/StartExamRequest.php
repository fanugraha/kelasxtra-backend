<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StartExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi sesungguhnya dicek via AccessControlService di controller
    }

    public function rules(): array
    {
        return [
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'exam_batch_id' => ['nullable', 'integer', 'exists:exam_batches,id'],
            // Nullable: Part Latihan Topik (soal diambil random lintas bank,
            // dibungkus jadi 1 section) tidak butuh bank_id sama sekali.
            // Wajib-tidaknya untuk exam try-out multi-bank divalidasi di
            // withValidator() di bawah -- bergantung pada exam_id yang dikirim,
            // jadi tidak bisa jadi rule statis di sini.
            'bank_id' => ['nullable', 'integer', 'exists:question_banks,id'],
        ];
    }

    /**
     * Validasi tambahan:
     * - Kalau exam ini punya section ber-bank (try-out multi-bank), bank_id
     *   WAJIB diisi dan harus salah satu bank yang benar-benar nempel ke exam ini.
     * - Kalau exam ini tidak punya section ber-bank sama sekali (Part Latihan
     *   Topik -- semua section-nya question_bank_id NULL), bank_id diabaikan
     *   total, tidak divalidasi apapun.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $examId = $this->input('exam_id');
            $bankId = $this->input('bank_id');

            if (!$examId) {
                return;
            }

            $bankIds = \Illuminate\Support\Facades\DB::table('exam_sections')
                ->join('exam_questions', 'exam_questions.exam_section_id', '=', 'exam_sections.id')
                ->where('exam_sections.exam_id', $examId)
                ->whereNotNull('exam_sections.question_bank_id')
                ->distinct()
                ->pluck('exam_sections.question_bank_id');

            if ($bankIds->isEmpty()) {
                // Part Latihan Topik / single-pool: bank_id tidak relevan.
                return;
            }

            if (!$bankId) {
                $validator->errors()->add(
                    'bank_id',
                    'Exam ini punya beberapa bank soal, silakan pilih salah satu.'
                );
                return;
            }

            if (!$bankIds->contains((int) $bankId)) {
                $validator->errors()->add(
                    'bank_id',
                    'Bank soal ini tidak tersedia untuk exam yang dipilih.'
                );
            }
        });
    }
}
