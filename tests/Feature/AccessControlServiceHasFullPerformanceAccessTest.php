<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Program;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlServiceHasFullPerformanceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected AccessControlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccessControlService();
    }

    public function test_granted_via_active_enrollment(): void
    {
        $program = Program::factory()->create();
        $package = Package::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create();

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->service->hasFullPerformanceAccess($user, $program->id));
    }

    public function test_granted_via_expired_enrollment(): void
    {
        // Paket lama tetap boleh lihat histori performanya -- perilaku lama, jangan regresi.
        $program = Program::factory()->create();
        $package = Package::factory()->create(['program_id' => $program->id]);
        $user = User::factory()->create();

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'expired',
        ]);

        $this->assertTrue($this->service->hasFullPerformanceAccess($user, $program->id));
    }

    public function test_granted_via_active_subscription_covering_program_without_any_enrollment(): void
    {
        // Ini gap yang diperbaiki: subscriber murni (tidak pernah punya Enrollment
        // sama sekali) harus tetap bisa lihat dashboard performanya sendiri, karena
        // itu satu-satunya jalur resmi dia mengerjakan Latihan Fokus (canAttemptExam()).
        $program = Program::factory()->create();
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->create(['program_id' => $program->id]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->service->hasFullPerformanceAccess($user, $program->id));
    }

    public function test_granted_via_multi_program_subscription_pivot(): void
    {
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->multiProgram(2)->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $subscription->programs()->sync([$program->id, $otherProgram->id]);

        $this->assertTrue($this->service->hasFullPerformanceAccess($user, $program->id));
    }

    public function test_blocked_with_subscription_to_a_different_program(): void
    {
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->create(['program_id' => $otherProgram->id]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->assertFalse($this->service->hasFullPerformanceAccess($user, $program->id));
    }

    public function test_blocked_with_expired_subscription_and_no_enrollment(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->create(['program_id' => $program->id]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'expired',
        ]);

        $this->assertFalse($this->service->hasFullPerformanceAccess($user, $program->id));
    }

    public function test_blocked_with_active_status_but_past_end_date_and_no_enrollment(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->create(['program_id' => $program->id]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'end_date' => now()->subDay(),
        ]);

        $this->assertFalse($this->service->hasFullPerformanceAccess($user, $program->id));
    }

    public function test_blocked_without_any_enrollment_or_subscription(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($this->service->hasFullPerformanceAccess($user, $program->id));
    }
}
