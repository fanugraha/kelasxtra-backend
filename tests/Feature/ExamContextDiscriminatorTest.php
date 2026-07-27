<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Program;
use App\Models\Taxonomy;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2 poin 6: exams.context sebagai discriminator eksplisit, menggantikan
 * whereNull('topic_id')/whereNotNull('part_number') yang tersebar. Fokus
 * test ini: context TIDAK BOLEH bisa drift dari topic_id, apapun cara
 * Exam-nya dibuat (factory, service, input manual admin).
 */
class ExamContextDiscriminatorTest extends TestCase
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

    public function test_context_defaults_to_tryout_when_topic_id_is_null(): void
    {
        $program = Program::factory()->create();

        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'topic_id' => null,
        ]);

        $this->assertSame(Exam::CONTEXT_TRYOUT, $exam->fresh()->context);
        $this->assertFalse($exam->fresh()->isTopicPractice());
    }

    public function test_context_auto_syncs_to_topic_practice_when_topic_id_is_set(): void
    {
        $program = Program::factory()->create();
        $topic = $this->makeTopic($program);

        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'topic_id' => $topic->id,
            'part_number' => 1,
        ]);

        $this->assertSame(Exam::CONTEXT_TOPIC_PRACTICE, $exam->fresh()->context);
        $this->assertTrue($exam->fresh()->isTopicPractice());
    }

    public function test_context_cannot_be_forced_out_of_sync_with_topic_id(): void
    {
        $program = Program::factory()->create();
        $topic = $this->makeTopic($program);

        // Sengaja coba "curang" set context='tryout' walau topic_id diisi --
        // ini yang harus mustahil terjadi, apapun caller-nya.
        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'topic_id' => $topic->id,
            'part_number' => 1,
            'context' => Exam::CONTEXT_TRYOUT,
        ]);

        $this->assertSame(Exam::CONTEXT_TOPIC_PRACTICE, $exam->fresh()->context);
    }

    public function test_tryout_scope_excludes_topic_practice_exams(): void
    {
        $program = Program::factory()->create();
        $topic = $this->makeTopic($program);

        $regular = Exam::factory()->create(['program_id' => $program->id, 'topic_id' => null]);
        Exam::factory()->create(['program_id' => $program->id, 'topic_id' => $topic->id, 'part_number' => 1]);

        $ids = Exam::tryout()->pluck('id');

        $this->assertTrue($ids->contains($regular->id));
        $this->assertCount(1, $ids);
    }

    public function test_topic_practice_scope_returns_only_topic_practice_exams(): void
    {
        $program = Program::factory()->create();
        $topic = $this->makeTopic($program);

        Exam::factory()->create(['program_id' => $program->id, 'topic_id' => null]);
        $part = Exam::factory()->create(['program_id' => $program->id, 'topic_id' => $topic->id, 'part_number' => 1]);

        $ids = Exam::topicPractice()->pluck('id');

        $this->assertTrue($ids->contains($part->id));
        $this->assertCount(1, $ids);
    }
}
