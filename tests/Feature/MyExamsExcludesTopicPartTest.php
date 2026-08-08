<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Program;
use App\Models\Taxonomy;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyExamsExcludesTopicPartTest extends TestCase
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

    public function test_my_exams_excludes_topic_part_even_when_accessible(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Exam biasa (bukan Part) -- free preview, harus muncul.
        $regularExam = Exam::factory()->create([
            'program_id' => $program->id,
            'is_free_preview' => true,
            'topic_id' => null,
        ]);

        // Exam Part topik -- Part 1, free preview (selalu accessible), TAPI
        // harus tetap TIDAK muncul di myExams() karena flow-nya beda halaman
        // (lihat TopicPracticeController).
        $topic = $this->makeTopic($program);
        $topicPart = Exam::factory()->create([
            'program_id' => $program->id,
            'topic_id' => $topic->id,
            'part_number' => 1,
            'is_free_preview' => true,
        ]);

        $response = $this->getJson('/api/my-exams');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');

        $this->assertTrue($ids->contains($regularExam->id));
        $this->assertFalse($ids->contains($topicPart->id));
    }

    public function test_latest_attempted_exam_fallback_never_returns_topic_part(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Sengaja TIDAK bikin exam biasa yang free preview -- cuma ada Exam
        // Part free preview di database. Kalau fix ini gagal, fallback bakal
        // balik ke Part itu (exam_id bukan null).
        $topic = $this->makeTopic($program);
        Exam::factory()->create([
            'program_id' => $program->id,
            'topic_id' => $topic->id,
            'part_number' => 1,
            'is_free_preview' => true,
        ]);

        $response = $this->getJson('/api/my-exams/latest-attempted');

        $response->assertOk();
        $response->assertJson(['exam_id' => null]);
    }

    public function test_latest_attempted_exam_primary_path_never_returns_topic_part(): void
    {
        $program = Program::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Bug yang sebenarnya terjadi: user SUDAH attempt exam Part
        // (bukan skenario "belum ada attempt sama sekali" seperti test
        // fallback di atas). Sebelum fix, primary $latestAttempt query
        // tidak filter tryout(), jadi exam Part ini bocor jadi exam_id
        // yang dibalikkan -- bikin Continue Card Beranda nyangkut di
        // exam Part yang sama terus-menerus.
        $topic = $this->makeTopic($program);
        $topicPart = Exam::factory()->create([
            'program_id' => $program->id,
            'topic_id' => $topic->id,
            'part_number' => 1,
            'is_free_preview' => true,
        ]);

        \App\Models\ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $topicPart->id,
            'status' => 'submitted',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $response = $this->getJson('/api/my-exams/latest-attempted');

        $response->assertOk();
        $response->assertJson(['exam_id' => null]);
    }
}
