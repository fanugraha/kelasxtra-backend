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

        // Exam hanya bisa diakses lewat paket yang secara eksplisit "membuka"
        // exam ini (relasi package_exam). Ini sengaja tidak lagi mengandalkan
        // kecocokan bank_id/program_id semata, karena itu membuat SEMUA exam
        // di bank soal yang sama otomatis ikut terbuka meski admin cuma mau
        // jual sebagian exam-nya lewat paket ini.
        return $user->enrollments()->active()
            ->whereHas('package.exams', fn ($q) => $q->where('exams.id', $exam->id))
            ->exists();
    }
}