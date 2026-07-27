<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Package;
use App\Models\Program;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlServiceSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected AccessControlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccessControlService();
    }

    protected function makeFocusTopicExam(Program $program): Exam
    {
        $exam = Exam::factory()->create([
            'is_free_preview' => false,
            'program_id' => $program->id,
        ]);

        $focusPackage = Package::factory()->create([
            'program_id' => $program->id,
            'is_focus_topic' => true,
        ]);
        $focusPackage->exams()->attach($exam->id);

        return $exam;
    }

    public function test_focus_topic_exam_accessible_with_active_subscription_covering_program(): void
    {
        $program = Program::factory()->create();
        $exam = $this->makeFocusTopicExam($program);
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->create(['program_id' => $program->id]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->assertTrue($this->service->canAttemptExam($user, $exam));
    }

    public function test_focus_topic_exam_blocked_with_subscription_to_different_program(): void
    {
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $exam = $this->makeFocusTopicExam($program);
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->create(['program_id' => $otherProgram->id]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->assertFalse($this->service->canAttemptExam($user, $exam));
    }

    public function test_focus_topic_exam_blocked_with_expired_subscription(): void
    {
        $program = Program::factory()->create();
        $exam = $this->makeFocusTopicExam($program);
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->create(['program_id' => $program->id]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'expired',
        ]);

        $this->assertFalse($this->service->canAttemptExam($user, $exam));
    }

    public function test_focus_topic_exam_blocked_with_active_status_but_past_end_date(): void
    {
        $program = Program::factory()->create();
        $exam = $this->makeFocusTopicExam($program);
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->create(['program_id' => $program->id]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'end_date' => now()->subDay(),
        ]);

        $this->assertFalse($this->service->canAttemptExam($user, $exam));
    }

    public function test_non_focus_topic_exam_not_granted_by_subscription_even_if_program_matches(): void
    {
        // Exam biasa (bukan is_focus_topic) tidak boleh terbuka lewat subscription,
        // meski program_id-nya cocok -- subscription cuma buka gerbang Latihan Fokus.
        $program = Program::factory()->create();
        $exam = Exam::factory()->create([
            'is_free_preview' => false,
            'program_id' => $program->id,
        ]);
        // Sengaja TIDAK di-attach ke package is_focus_topic manapun.

        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create(['program_id' => $program->id]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->assertFalse($this->service->canAttemptExam($user, $exam));
    }

    public function test_focus_topic_exam_accessible_via_multi_program_plan_pivot(): void
    {
        $program = Program::factory()->create();
        $otherProgram = Program::factory()->create();
        $exam = $this->makeFocusTopicExam($program);
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->multiProgram(2)->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $subscription->programs()->sync([$program->id, $otherProgram->id]);

        $this->assertTrue($this->service->canAttemptExam($user, $exam));
    }

    public function test_focus_topic_exam_blocked_via_multi_program_plan_when_program_not_selected(): void
    {
        $program = Program::factory()->create();
        $otherProgramA = Program::factory()->create();
        $otherProgramB = Program::factory()->create();
        $exam = $this->makeFocusTopicExam($program);
        $user = User::factory()->create();

        $plan = SubscriptionPlan::factory()->multiProgram(2)->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        // Subscription cover 2 program lain, bukan $program milik exam.
        $subscription->programs()->sync([$otherProgramA->id, $otherProgramB->id]);

        $this->assertFalse($this->service->canAttemptExam($user, $exam));
    }

    public function test_focus_topic_exam_blocked_without_any_subscription(): void
    {
        $program = Program::factory()->create();
        $exam = $this->makeFocusTopicExam($program);
        $user = User::factory()->create();

        $this->assertFalse($this->service->canAttemptExam($user, $exam));
    }

    public function test_focus_topic_exam_not_granted_by_enrollment_even_when_package_explicitly_links_it(): void
    {
        // Keputusan produk: exam focus-topic HANYA lewat Subscription, Enrollment
        // tidak berlaku sama sekali untuk jenis exam ini -- meski (misal karena
        // kesalahan admin) sebuah Package biasa ikut memuat exam ini via package_exam
        // dan user punya Enrollment aktif ke Package itu, akses tetap ditolak.
        $program = Program::factory()->create();
        $exam = $this->makeFocusTopicExam($program);
        $user = User::factory()->create();

        $regularPackage = Package::factory()->create([
            'program_id' => $program->id,
            'is_focus_topic' => false,
        ]);
        $regularPackage->exams()->attach($exam->id);

        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $regularPackage->id,
            'status' => 'active',
        ]);

        $this->assertFalse($this->service->canAttemptExam($user, $exam));
    }
}
