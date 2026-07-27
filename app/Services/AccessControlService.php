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

    /**
     * Cek apakah user punya Subscription aktif yang meng-cover $programId --
     * dipakai bareng oleh canAttemptExam() (gerbang Latihan Fokus) dan
     * hasFullPerformanceAccess() (gerbang dashboard performa), supaya
     * "siapa yang punya akses subscription ke program ini" cuma
     * didefinisikan di satu tempat.
     */
    protected function hasActiveSubscriptionCoveringProgram(User $user, int $programId): bool
    {
        return $user->subscriptions()->active()
            ->get()
            ->contains(fn ($subscription) => $subscription->coversProgram($programId));
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
     * Jenis exam menentukan gerbang akses secara EKSKLUSIF (bukan OR):
     *  - Exam Latihan Fokus (exam terhubung ke Package dengan
     *    is_focus_topic = true) -- HANYA lewat Subscription aktif yang
     *    meng-cover Program exam ini (Subscription::coversProgram()).
     *    Enrollment/Package TIDAK berlaku untuk exam jenis ini sama
     *    sekali -- ini keputusan produk resmi, bukan cuma default kalau
     *    Enrollment tidak ada. Kalau suatu saat admin iseng mengaitkan
     *    Package biasa ke exam focus-topic, itu tidak membuka akses.
     *  - Exam biasa (bukan focus-topic) -- HANYA lewat Enrollment aktif
     *    ke Package yang memuat Exam ini -- jalur pembelian satuan/reguler.
     */
    public function canAttemptExam(User $user, Exam $exam): bool
    {
        if ($exam->is_free_preview) {
            return true;
        }

        $isFocusTopicExam = $exam->packages()->where('packages.is_focus_topic', true)->exists();

        if ($isFocusTopicExam) {
            if (blank($exam->program_id)) {
                return false;
            }

            return $this->hasActiveSubscriptionCoveringProgram($user, $exam->program_id);
        }

        return $user->enrollments()->active()
            ->whereHas('package.exams', fn ($q) => $q->where('exams.id', $exam->id))
            ->exists();
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
        if (! $exam->isTopicPractice()) {
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
     * untuk sebuah program. Ada 2 jalur, SAMA seperti canAttemptExam():
     *  - Enrollment aktif (atau expired -- paket lama tetap boleh lihat
     *    histori performanya) ke Package yang menaungi Program ini.
     *  - Subscription aktif yang meng-cover Program ini -- ini jalur
     *    RESMI untuk Latihan Fokus (canAttemptExam() cuma buka lewat
     *    Subscription untuk exam focus-topic), jadi siswa yang cuma
     *    berlangganan (tanpa pernah punya Enrollment sama sekali) tetap
     *    harus bisa lihat dashboard performa hasil Latihan Fokus-nya
     *    sendiri. Sebelumnya method ini HANYA cek Enrollment, sehingga
     *    subscriber murni ditolak melihat performanya sendiri walau
     *    sudah boleh mengerjakan latihannya.
     */
    public function hasFullPerformanceAccess(User $user, int $programId): bool
    {
        $hasEnrollmentAccess = Enrollment::query()
            ->where('user_id', $user->id)
            ->whereHas('package', function ($q) use ($programId) {
                $q->where('program_id', $programId);
            })
            ->where(function ($q) {
                $q->active()->orWhere('status', 'expired');
            })
            ->exists();

        if ($hasEnrollmentAccess) {
            return true;
        }

        return $this->hasActiveSubscriptionCoveringProgram($user, $programId);
    }
}
