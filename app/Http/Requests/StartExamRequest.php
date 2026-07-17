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
            // Wajib: siswa harus pilih bank soal mana yang dikerjakan (1 exam
            // bisa jual beberapa bank sekaligus, tapi tiap attempt terikat 1 bank).
            'bank_id' => ['required', 'integer', 'exists:question_banks,id'],
        ];
    }

    /**
     * Validasi tambahan: bank_id yang dikirim harus benar-benar salah satu
     * bank yang soalnya nempel ke exam_id ini -- supaya tidak bisa attempt
     * pakai bank yang tidak dijual/tidak terkait exam tersebut.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $examId = $this->input('exam_id');
            $bankId = $this->input('bank_id');

            if (!$examId || !$bankId) {
                return;
            }

            $bankExistsInExam = \App\Models\Exam::find($examId)
                ?->questions()
                ->whereHas('bank', fn ($q) => $q->where('question_banks.id', $bankId))
                ->exists();

            if (!$bankExistsInExam) {
                $validator->errors()->add(
                    'bank_id',
                    'Bank soal ini tidak tersedia untuk exam yang dipilih.'
                );
            }
        });
    }
}
