<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Satu soal bisa tipe 'pg' (kirim selected_option_id) atau 'essay'
 * (kirim essay_answer). Salah satu wajib ada, tidak boleh dua-duanya kosong.
 */
class SubmitAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'selected_option_id' => ['nullable', 'integer', 'exists:question_options,id', 'required_without:essay_answer'],
            'essay_answer' => ['nullable', 'string', 'required_without:selected_option_id'],
        ];
    }
}
