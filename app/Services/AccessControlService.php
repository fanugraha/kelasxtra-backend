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
     * Akses dasar untuk sebuah Exam (baik Exam satuan biasa maupun Exam
     * Part topik) -- Exam Part tetap harus lewat method ini dulu sebelum
     * canAccessExamPart() menambahkan cek urutan Part di atasnya.
     *
     * Ada 2 jalur "dimiliki" yang berbeda:
     *  - Lewat Package biasa (Enrollment aktif ke Package yang memuat
     *    Exam ini) -- jalur pembelian satuan/reguler.
     *  - Lewat Package Latihan Fokus (package.is_focus_topic = true yang
     *    memuat Exam ini) -- jalur ini TIDAK dibuka lewat Enrollment,
     *    melainkan lewat Subscription aktif yang meng-cover Program Exam
     *    ini (Subscription::coversProgram()).
     */
    public function canAttemptExam(User $user, Exam $exam): bool
    {
        if ($exam->is_free_preview) {
            return true;
        }

        $hasEnrollmentAccess = $user->enrollments()->active()
            ->whereHas('package.exams', fn ($q) => $q->where('exams.id', $exam->id))
            ->exists();

        if ($hasEnrollmentAccess) {
            return true;
        }

        $isFocusTopicExam = $exam->packages()->where('packages.is_focus_topic', true)->exists();

        if (! $isFocusTopicExam) {
            return false;
        }

        if (blank($exam->program_id)) {
            return false;
        }

        return $user->subscriptions()->active()
            ->get()
            ->contains(fn ($subscription) => $subscription->coversProgram($exam->program_id));
    }

    /**
     * Akses untuk Latihan Soal per Part/Topik. Aturan kepemilikan (Package
     * biasa vs Package Latihan Fokus + Subscription) SAMA PERSIS dengan
     * canAttemptExam() di atas -- yang membedakan Part dari Exam satuan
     * cuma satu hal tambahan: harus urut, Part sebelumnya WAJIB sudah
     * SELESAI dulu (bukan cuma "sudah dibuka"). Part yang sudah selesai
     * boleh diulang bebas kapan saja (lewat canReattemptCompletedPart(),
     * bukan lewat cek ini).
     */
    public function canAccessExamPart(User $user, Exam $exam): bool
    {
        if (blank($exam->topic_id)) {
            // Bukan Part sama sekali -- pakai aturan Exam satuan biasa.
            return $this->canAttemptExam($user, $exam);
        }

        if (! $this->canAttemptExam($user, $exam)) {
            return false;
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
