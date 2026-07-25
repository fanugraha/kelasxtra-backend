<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Package;
use App\Models\Program;
use App\Models\Topic;
use App\Models\Taxonomy;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlServiceCanAccessExamPartTest extends TestCase
{
    use RefreshDatabase;

    protected function makePackage(Program $program): Package
    {
        return Package::factory()->create([
            'program_id' => $program->id,
        ]);
    }

    protected function enrollUser(User $user, Package $package): void
    {
        Enrollment::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'end_date' => null,
        ]);
    }

    protected function makeTopic(Program $program, string $code = 'PIL'): Topic
    {
        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => $code,
            'name' => $code,
        ]);

        return Topic::create([
            'taxonomy_id' => $taxonomy->id,
            'code' => $code,
            'name' => $code,
        ]);
    }

    // Bikin exam part DAN attach ke package lewat pivot package_exam --
    // canAttemptExam() mensyaratkan relasi eksplisit ini, bukan cuma
    // kecocokan program_id.
    protected function makeExamPart(Topic $topic, Program $program, Package $package, int $partNumber): Exam
    {
        $exam = Exam::create([
            'topic_id' => $topic->id,
            'part_number' => $partNumber,
            'program_id' => $program->id,
            'focus_mode' => 'all_program',
            'title' => "Part {$partNumber}",
            'duration_minutes' => 30,
            'passing_score' => null,
            'require_all_sections_pass' => false,
            'uses_section_timers' => false,
            'is_free_preview' => false,
        ]);

        $package->exams()->attach($exam->id);

        return $exam;
    }

    public function test_part_one_is_always_accessible(): void
    {
        $program = Program::factory()->create();
        $package = $this->makePackage($program);
        $user = User::factory()->create();
        $this->enrollUser($user, $package);
        $topic = $this->makeTopic($program);

        $part1 = $this->makeExamPart($topic, $program, $package, 1);

        $this->assertTrue(app(AccessControlService::class)->canAccessExamPart($user, $part1));
    }

    public function test_part_two_is_locked_when_part_one_not_completed(): void
    {
        $program = Program::factory()->create();
        $package = $this->makePackage($program);
        $user = User::factory()->create();
        $this->enrollUser($user, $package);
        $topic = $this->makeTopic($program);

        $this->makeExamPart($topic, $program, $package, 1);
        $part2 = $this->makeExamPart($topic, $program, $package, 2);

        $this->assertFalse(app(AccessControlService::class)->canAccessExamPart($user, $part2));
    }

    public function test_part_two_is_unlocked_after_part_one_submitted(): void
    {
        $program = Program::factory()->create();
        $package = $this->makePackage($program);
        $user = User::factory()->create();
        $this->enrollUser($user, $package);
        $topic = $this->makeTopic($program);

        $part1 = $this->makeExamPart($topic, $program, $package, 1);
        $part2 = $this->makeExamPart($topic, $program, $package, 2);

        ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $part1->id,
            'status' => 'submitted',
        ]);

        $this->assertTrue(app(AccessControlService::class)->canAccessExamPart($user, $part2));
    }

    public function test_part_two_stays_locked_when_part_one_attempt_still_in_progress(): void
    {
        $program = Program::factory()->create();
        $package = $this->makePackage($program);
        $user = User::factory()->create();
        $this->enrollUser($user, $package);
        $topic = $this->makeTopic($program);

        $part1 = $this->makeExamPart($topic, $program, $package, 1);
        $part2 = $this->makeExamPart($topic, $program, $package, 2);

        ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $part1->id,
            'status' => 'in_progress',
        ]);

        $this->assertFalse(app(AccessControlService::class)->canAccessExamPart($user, $part2));
    }

    public function test_locked_status_is_per_user_not_global(): void
    {
        $program = Program::factory()->create();
        $package = $this->makePackage($program);
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->enrollUser($userA, $package);
        $this->enrollUser($userB, $package);
        $topic = $this->makeTopic($program);

        $part1 = $this->makeExamPart($topic, $program, $package, 1);
        $part2 = $this->makeExamPart($topic, $program, $package, 2);

        ExamAttempt::factory()->create([
            'user_id' => $userA->id,
            'exam_id' => $part1->id,
            'status' => 'submitted',
        ]);

        $this->assertTrue(app(AccessControlService::class)->canAccessExamPart($userA, $part2));
        $this->assertFalse(app(AccessControlService::class)->canAccessExamPart($userB, $part2));
    }

    public function test_user_without_enrollment_cannot_access_any_part(): void
    {
        // Kasus tambahan: user sama sekali tidak enrolled ke package manapun
        // yang membuka exam ini -- harus gagal sejak part 1.
        $program = Program::factory()->create();
        $package = $this->makePackage($program);
        $user = User::factory()->create();
        $topic = $this->makeTopic($program);

        $part1 = $this->makeExamPart($topic, $program, $package, 1);

        $this->assertFalse(app(AccessControlService::class)->canAccessExamPart($user, $part1));
    }
}
