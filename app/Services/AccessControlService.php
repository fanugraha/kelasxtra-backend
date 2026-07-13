<?php

namespace App\Services;

use App\Models\ClassRoom;
use App\Models\Exam;
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

    public function canAttemptExam(User $user, Exam $exam): bool
    {
        if ($exam->is_free_preview) {
            return true;
        }

        // Bank soal hanya bisa diakses lewat paket yang secara eksplisit
        // "membuka" bank soal ini (relasi package_question_bank). Ini sengaja
        // tidak lagi mengandalkan kecocokan program_id/subject_id semata,
        // karena itu membuat SEMUA bank soal di program yang sama otomatis
        // ikut terbuka meski user tidak membeli paket untuk bank soal tsb.
        return $user->enrollments()->active()
            ->whereHas('package.questionBanks', fn ($q) => $q->where('question_banks.id', $exam->bank_id))
            ->exists();
    }
}