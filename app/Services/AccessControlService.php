<?php

namespace App\Services;

use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Material;
use App\Models\User;

class AccessControlService
{
    protected function hasActiveEnrollmentFor(User $user, string $column, int $value): bool
    {
        return $user->enrollments()->active()->where($column, $value)->exists();
    }

    public function canAccessClass(User $user, ClassRoom $class): bool
    {
        // Siswa bisa akses kelas kalau enrollment-nya nempel langsung ke class_id ini,
        // ATAU enrollment ke package yang menaungi class ini (belum tentu pilih kelas tertentu).
        return $this->hasActiveEnrollmentFor($user, 'class_id', $class->id)
            || $this->hasActiveEnrollmentFor($user, 'package_id', $class->package_id);
    }

    public function canAccessMaterial(User $user, Material $material): bool
    {
        return $this->canAccessClass($user, $material->classRoom);
    }

    /**
     * Akses untuk Exam SATUAN (dijual lewat Package/Enrollment biasa).
     * Exam Part (topic_id terisi) TIDAK memakai method ini -- lihat
     * canAccessExamPart() untuk aturannya sendiri.
     */
    public function canAttemptExam(User $user, Exam $exam): bool
    {
        if ($exam->is_free_preview) {
            return true;
        }

        $hasEnrollmentAccess = $user->enrollments()->active()
            ->whereHas('package.exams', fn ($q) => $q->where('exams.id', $exam->id))
            ->exists();

        return $hasEnrollmentAccess;
    }

    /**
     * Akses untuk Latihan Soal per Part/Topik -- ini katalog TERBUKA,
     * bukan sesuatu yang "dimiliki" lewat Package/Enrollment:
     *
     *  - Part 1 tiap topik SELALU terbuka untuk siapa saja yang login
     *    (is_free_preview = true, di-set otomatis oleh TopicPartGenerator).
     *  - Part 2 dst butuh Subscription AKTIF yang meng-cover Program exam
     *    ini (dicek langsung ke Program, bukan lewat kepemilikan Package).
     *  - Tetap wajib urut: Part sebelumnya harus SUDAH SELESAI dulu, bukan
     *    cuma "sudah dibuka". Part yang sudah selesai boleh diulang bebas
     *    kapan saja (lewat canReattemptCompletedPart(), bukan lewat cek ini).
     */
    public function canAccessExamPart(User $user, Exam $exam): bool
    {
        if (blank($exam->topic_id)) {
            // Bukan Part sama sekali -- pakai aturan Exam satuan biasa.
            return $this->canAttemptExam($user, $exam);
        }

        if (! $exam->is_free_preview) {
            if (blank($exam->program_id)) {
                return false;
            }

            $hasSubscription = $user->subscriptions()->active()
                ->get()
                ->contains(fn ($subscription) => $subscription->coversProgram($exam->program_id));

            if (! $hasSubscription) {
                return false;
            }
        }

        return $this->previousPartCompleted($user, $exam);
    }

    /**
     * Cek apakah Part sebelum $exam (part_number - 1) sudah DISELESAIKAN
     * siswa ini. Part 1 atau exam tanpa part_number dianggap selalu "lolos"
     * cek ini (tidak ada prasyarat).
     */
    protected function previousPartCompleted(User $user, Exam $exam): bool
    {
        if (blank($exam->part_number) || $exam->part_number <= 1) {
            return true;
        }

        $previousPart = Exam::where('topic_id', $exam->topic_id)
            ->where('part_number', $exam->part_number - 1)
            ->first();

        if (! $previousPart) {
            return true;
        }

        return ExamAttempt::where('user_id', $user->id)
            ->where('exam_id', $previousPart->id)
            ->whereIn('status', ['submitted', 'auto_submitted', 'graded'])
            ->exists();
    }

    /**
     * Cek apakah user punya akses full ke breakdown topik & rekomendasi
     * untuk sebuah program, berdasarkan enrollment aktif (pakai scopeActive
     * dari model Enrollment supaya definisi "aktif" cuma di satu tempat)
     * atau enrollment yang sudah expired (paket lama tetap bisa dilihat).
     */
    public function hasFullPerformanceAccess(User $user, int $programId): bool
    {
        return Enrollment::query()
            ->where('user_id', $user->id)
            ->whereHas('package', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            })
            ->where(function ($q) {
                $q->active()->orWhere('status', 'expired');
            })
            ->exists();
    }
}
