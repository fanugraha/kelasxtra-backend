<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Package;
use App\Models\Question;
use App\Models\Topic;
use App\Models\TopicUsedQuestion;
use Illuminate\Support\Facades\DB;

class TopicPartGenerator
{
    public function generateNextPart(Topic $topic, int $questionCount = 10): Exam
    {
        $usedQuestionIds = TopicUsedQuestion::where('topic_id', $topic->id)
            ->pluck('question_id');

        $availableCount = Question::where('topic_id', $topic->id)
            ->whereNotIn('id', $usedQuestionIds)
            ->count();

        if ($availableCount < $questionCount) {
            throw new \RuntimeException(
                "Stok soal topik \"{$topic->name}\" tinggal {$availableCount}, " .
                "butuh {$questionCount} untuk generate part baru. Tambah soal dulu."
            );
        }

        $programId = $topic->taxonomy->program_id;

        // Cari package LANGGANAN LATIHAN FOKUS untuk program ini -- WAJIB
        // filter is_focus_topic, karena satu program bisa punya banyak Package
        // (tryout reguler, privat, dll) dan exam part ini HANYA boleh nempel
        // ke package langganan, bukan package apa pun yang kebetulan ketemu
        // duluan. Prioritaskan package yang focus_taxonomy_id-nya cocok persis
        // dengan topik ini; fallback ke package "seluruh program" (focus_taxonomy_id null).
        //
        // CATATAN: sejak Latihan Soal per Part dipisah jadi katalog terbuka
        // sendiri (lihat TopicPracticeController), atribut Package ini
        // TIDAK LAGI dipakai untuk cek akses siswa (itu sekarang murni
        // Subscription->coversProgram()). Package di sini masih dipertahankan
        // sebagai cara admin mengelompokkan Exam Part di Filament saja.
        $matchingPackages = Package::where('program_id', $programId)
            ->where('is_focus_topic', true)
            ->where(function ($q) use ($topic) {
                $q->where('focus_taxonomy_id', $topic->taxonomy_id)
                    ->orWhereNull('focus_taxonomy_id');
            })
            ->get();

        if ($matchingPackages->isEmpty()) {
            throw new \RuntimeException(
                "Tidak ada Package langganan (is_focus_topic=true) untuk program_id={$programId} " .
                "topik \"{$topic->name}\". Buat Package langganan Latihan Fokus dulu sebelum generate part."
            );
        }

        $exactMatches = $matchingPackages->where('focus_taxonomy_id', $topic->taxonomy_id);

        if ($exactMatches->count() > 1) {
            $ids = $exactMatches->pluck('id')->implode(', ');
            throw new \RuntimeException(
                "Ada lebih dari 1 Package langganan yang cocok persis untuk topik \"{$topic->name}\" " .
                "(Package #{$ids}). Sistem tidak bisa memilih otomatis -- hapus/rapikan salah satu package dulu, " .
                "atau hubungi developer untuk menentukan package mana yang benar."
            );
        }

        if ($exactMatches->count() === 1) {
            $package = $exactMatches->first();
        } else {
            $universalMatches = $matchingPackages->whereNull('focus_taxonomy_id');

            if ($universalMatches->count() > 1) {
                $ids = $universalMatches->pluck('id')->implode(', ');
                throw new \RuntimeException(
                    "Ada lebih dari 1 Package langganan universal (berlaku untuk semua topik) di program ini " .
                    "(Package #{$ids}). Sistem tidak bisa memilih otomatis -- hapus/rapikan salah satu package dulu, " .
                    "atau hubungi developer untuk menentukan package mana yang benar."
                );
            }

            $package = $universalMatches->first();
        }

        $questions = Question::where('topic_id', $topic->id)
            ->whereNotIn('id', $usedQuestionIds)
            ->inRandomOrder()
            ->limit($questionCount)
            ->with('bank')
            ->get();

        $nextPart = (Exam::where('topic_id', $topic->id)->max('part_number') ?? 0) + 1;

        return DB::transaction(function () use ($topic, $questions, $nextPart, $questionCount, $package, $programId) {
            $exam = Exam::create([
                'program_id' => $programId,
                'title' => "{$topic->name} - Part {$nextPart}",
                'topic_id' => $topic->id,
                'part_number' => $nextPart,
                // Estimasi 1.5 menit per soal, dibulatkan ke atas, minimal 5 menit.
                'duration_minutes' => max(5, (int) ceil($questionCount * 1.5)),
                // Part 1 tiap topik otomatis gratis -- ini "sample rasa" funnel:
                // siswa bisa coba kualitas soal tanpa subscribe dulu. Part 2
                // dst butuh Subscription aktif (lihat AccessControlService).
                'is_free_preview' => $nextPart === 1,
            ]);

            $package->exams()->attach($exam->id);

            $questionsByBank = $questions->groupBy('bank_id');

            foreach ($questionsByBank as $bankId => $bankQuestions) {
                $bank = $bankQuestions->first()->bank;

                $section = $exam->sections()->create([
                    'taxonomy_id' => $topic->taxonomy_id,
                    'question_bank_id' => $bankId,
                    'code' => $topic->code,
                    'name' => $topic->name,
                    'scoring_type' => $bank->scoring_type,
                ]);

                foreach ($bankQuestions as $question) {
                    $exam->questions()->attach($question->id, [
                        'exam_section_id' => $section->id,
                    ]);

                    TopicUsedQuestion::create([
                        'topic_id' => $topic->id,
                        'question_id' => $question->id,
                        'exam_id' => $exam->id,
                    ]);
                }
            }

            return $exam;
        });
    }
}
