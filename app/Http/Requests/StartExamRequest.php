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
     * - Default sekarang: bank_id KOSONG selalu valid, apa pun jumlah bank
     *   yang nempel ke exam ini -- artinya siswa mengerjakan gabungan semua
     *   bank sekaligus (mis. TO SKD = TWK+TIU+TKP jadi 1 attempt). Ini bukan
     *   lagi kasus khusus Part Latihan Topik saja, tapi perilaku standar
     *   untuk SEMUA exam (lihat diskusi soal "gabung vs pecah").
     * - Kalau bank_id justru DIISI (mis. lewat ?bank= di URL, jalur mode
     *   split yang belum dipakai lagi tapi tetap didukung), dia harus salah
     *   satu bank yang benar-benar nempel ke exam ini -- supaya tidak bisa
     *   attempt pakai bank yang tidak terkait exam tersebut.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $examId = $this->input('exam_id');
            $bankId = $this->input('bank_id');

            if (!$examId || !$bankId) {
                // bank_id kosong: selalu valid, tidak perlu dicek lebih lanjut.
                return;
            }

            $bankIds = \Illuminate\Support\Facades\DB::table('exam_sections')
                ->join('exam_questions', 'exam_questions.exam_section_id', '=', 'exam_sections.id')
                ->where('exam_sections.exam_id', $examId)
                ->whereNotNull('exam_sections.question_bank_id')
                ->distinct()
                ->pluck('exam_sections.question_bank_id');

            if (!$bankIds->contains((int) $bankId)) {
                $validator->errors()->add(
                    'bank_id',
                    'Bank soal ini tidak tersedia untuk exam yang dipilih.'
                );
            }
        });
    }
}
