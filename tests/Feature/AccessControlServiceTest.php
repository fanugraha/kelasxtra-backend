<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Material;
use App\Models\Package;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AccessControlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccessControlService();
    }

    // ---------- canAttemptExam ----------

    public function test_free_preview_exam_is_always_accessible(): void
    {
        $user = User::factory()->create();
        $exam = Exam::factory()->create(['is_free_preview' => true]);

        $this->assertTrue($this->service->canAttemptExam($user, $exam));
    }

    public function test_paid_exam_blocked_without_enrollment(): void
    {
        $user = User::factory()->create();
        $exam = Exam::factory()->create(['is_free_preview' => false]);

        $this->assertFalse($this->service->canAttemptExam($user, $exam));
    }

    public function test_paid_exam_accessible_with_active_enrollment_to_package_containing_it(): void
    {
        $user = User::factory()->create();
        $package = Package::factory()->create();
        $exam = Exam::factory()->create(['is_free_preview' => false]);
        $package->exams()->attach($exam->id);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->service->canAttemptExam($user, $exam));
    }

    public function test_paid_exam_blocked_if_owned_package_does_not_include_it(): void
    {
        // Regression test untuk bug yang pernah kejadian: kecocokan program/bank
        // saja TIDAK boleh cukup -- harus lewat pivot package_exam eksplisit.
        $user = User::factory()->create();
        $package = Package::factory()->create();
        $examInPackage = Exam::factory()->create(['is_free_preview' => false]);
        $examNotInPackage = Exam::factory()->create(['is_free_preview' => false]);
        $package->exams()->attach($examInPackage->id);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->service->canAttemptExam($user, $examInPackage));
        $this->assertFalse($this->service->canAttemptExam($user, $examNotInPackage));
    }

    public function test_paid_exam_blocked_with_inactive_status_enrollment(): void
    {
        $user = User::factory()->create();
        $package = Package::factory()->create();
        $exam = Exam::factory()->create(['is_free_preview' => false]);
        $package->exams()->attach($exam->id);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'expired',
        ]);

        $this->assertFalse($this->service->canAttemptExam($user, $exam));
    }

    public function test_paid_exam_blocked_with_active_status_but_past_end_date(): void
    {
        // scopeActive butuh status='active' DAN (end_date null ATAU end_date >=
        // hari ini) -- jadi status 'active' dengan end_date kemarin harus tetap
        // dianggap tidak aktif.
        $user = User::factory()->create();
        $package = Package::factory()->create();
        $exam = Exam::factory()->create(['is_free_preview' => false]);
        $package->exams()->attach($exam->id);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'end_date' => now()->subDay(),
        ]);

        $this->assertFalse($this->service->canAttemptExam($user, $exam));
    }

    public function test_paid_exam_accessible_with_active_enrollment_and_null_end_date(): void
    {
        $user = User::factory()->create();
        $package = Package::factory()->create();
        $exam = Exam::factory()->create(['is_free_preview' => false]);
        $package->exams()->attach($exam->id);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'end_date' => null,
        ]);

        $this->assertTrue($this->service->canAttemptExam($user, $exam));
    }

    // ---------- hasFullPerformanceAccess ----------

    public function test_full_performance_access_granted_with_active_enrollment_to_program(): void
    {
        $user = User::factory()->create();
        $package = Package::factory()->create();

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->service->hasFullPerformanceAccess($user, $package->program_id));
    }

    public function test_full_performance_access_granted_with_completed_enrollment(): void
    {
        $user = User::factory()->create();
        $package = Package::factory()->create();

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'completed',
        ]);

        $this->assertTrue($this->service->hasFullPerformanceAccess($user, $package->program_id));
    }

    public function test_full_performance_access_denied_for_different_program(): void
    {
        $user = User::factory()->create();
        $package = Package::factory()->create();

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $otherProgramId = $package->program_id + 1;

        $this->assertFalse($this->service->hasFullPerformanceAccess($user, $otherProgramId));
    }

    public function test_full_performance_access_denied_with_expired_enrollment(): void
    {
        $user = User::factory()->create();
        $package = Package::factory()->create();

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'expired',
        ]);

        $this->assertFalse($this->service->hasFullPerformanceAccess($user, $package->program_id));
    }

    // ---------- canAccessClass ----------

    public function test_can_access_class_via_direct_class_enrollment(): void
    {
        $user = User::factory()->create();
        $class = ClassRoom::factory()->create();

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'class_id' => $class->id,
            'package_id' => $class->package_id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->service->canAccessClass($user, $class));
    }

    public function test_can_access_class_via_package_enrollment(): void
    {
        $user = User::factory()->create();
        $package = Package::factory()->create();
        $class = ClassRoom::factory()->create(['package_id' => $package->id]);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'class_id' => null,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->service->canAccessClass($user, $class));
    }

    public function test_cannot_access_unrelated_class(): void
    {
        $user = User::factory()->create();
        $class = ClassRoom::factory()->create();

        $this->assertFalse($this->service->canAccessClass($user, $class));
    }

    public function test_cannot_access_class_from_different_package_enrollment(): void
    {
        $user = User::factory()->create();
        $otherPackage = Package::factory()->create();
        $class = ClassRoom::factory()->create(); // package_id beda dari $otherPackage

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'class_id' => null,
            'package_id' => $otherPackage->id,
            'status' => 'active',
        ]);

        $this->assertFalse($this->service->canAccessClass($user, $class));
    }

    // ---------- canAccessMaterial ----------

    public function test_can_access_material_follows_class_access(): void
    {
        $user = User::factory()->create();
        $class = ClassRoom::factory()->create();
        $material = Material::factory()->create(['class_id' => $class->id]);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'class_id' => $class->id,
            'package_id' => $class->package_id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->service->canAccessMaterial($user, $material));
    }

    public function test_cannot_access_material_without_class_access(): void
    {
        $user = User::factory()->create();
        $class = ClassRoom::factory()->create();
        $material = Material::factory()->create(['class_id' => $class->id]);

        $this->assertFalse($this->service->canAccessMaterial($user, $material));
    }
}
