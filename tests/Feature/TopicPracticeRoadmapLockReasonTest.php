<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Package;
use App\Models\Program;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Taxonomy;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TopicPracticeRoadmapLockReasonTest extends TestCase
{
    use RefreshDatabase;

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

    protected function makePart(Topic $topic, Program $program, int $partNumber, bool $freePreview = false): Exam
    {
        return Exam::factory()->create([
            'topic_id' => $topic->id,
            'program_id' => $program->id,
            'part_number' => $partNumber,
            'is_free_preview' => $freePreview,
        ]);
    }

    protected function subscribeUser(User $user, Program $program): void
    {
        $plan = SubscriptionPlan::factory()->create(['program_id' => $program->id]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    /**
     * Regresi untuk Temuan 2: Part yang tidak pernah di-attach ke Package
     * is_focus_topic manapun ("yatim") HARUS dilaporkan locked_subscription
     * (mengikuti canAttemptExam() sebagai satu sumber kebenaran), BUKAN
     * locked_sequence yang menyesatkan siswa untuk "menyelesaikan Part 1
     * dulu" padahal itu tidak akan menyelesaikan apa-apa.
     */
    public function test_orphan_part_reports_locked_subscription_not_locked_sequence_even_with_valid_subscription(): void
    {
        $program = Program::factory()->create();
        $topic = $this->makeTopic($program);
        $this->makePart($topic, $program, 1, freePreview: true);
        $part2 = $this->makePart($topic, $program, 2, freePreview: false);
        // Sengaja TIDAK di-attach ke package is_focus_topic manapun.

        $user = User::factory()->create();
        $this->subscribeUser($user, $program);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/latihan-soal/topics/{$topic->id}/roadmap");

        $response->assertOk();
        $part2Data = collect($response->json())->firstWhere('exam_id', $part2->id);

        $this->assertSame('locked_subscription', $part2Data['status']);
    }

    public function test_properly_configured_part_reports_locked_sequence_when_previous_part_not_completed(): void
    {
        $program = Program::factory()->create();
        $topic = $this->makeTopic($program);
        $part1 = $this->makePart($topic, $program, 1, freePreview: true);
        $part2 = $this->makePart($topic, $program, 2, freePreview: false);

        $focusPackage = Package::factory()->create([
            'program_id' => $program->id,
            'is_focus_topic' => true,
        ]);
        $focusPackage->exams()->attach([$part1->id, $part2->id]);

        $user = User::factory()->create();
        $this->subscribeUser($user, $program);
        Sanctum::actingAs($user);
        // Part 1 sengaja belum diselesaikan.

        $response = $this->getJson("/api/latihan-soal/topics/{$topic->id}/roadmap");

        $response->assertOk();
        $part2Data = collect($response->json())->firstWhere('exam_id', $part2->id);

        $this->assertSame('locked_sequence', $part2Data['status']);
    }

    public function test_properly_configured_part_reports_locked_subscription_without_subscription(): void
    {
        $program = Program::factory()->create();
        $topic = $this->makeTopic($program);
        $part1 = $this->makePart($topic, $program, 1, freePreview: true);
        $part2 = $this->makePart($topic, $program, 2, freePreview: false);

        $focusPackage = Package::factory()->create([
            'program_id' => $program->id,
            'is_focus_topic' => true,
        ]);
        $focusPackage->exams()->attach([$part1->id, $part2->id]);

        $user = User::factory()->create();
        // TIDAK subscribe sama sekali.
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/latihan-soal/topics/{$topic->id}/roadmap");

        $response->assertOk();
        $part2Data = collect($response->json())->firstWhere('exam_id', $part2->id);

        $this->assertSame('locked_subscription', $part2Data['status']);
    }
}
