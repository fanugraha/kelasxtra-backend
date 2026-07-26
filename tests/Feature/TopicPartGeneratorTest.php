<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Package;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Topic;
use App\Models\TopicUsedQuestion;
use App\Models\Taxonomy;
use App\Services\TopicPartGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicPartGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTopic(string $name = 'Pilar Negara', string $code = 'PIL'): Topic
    {
        $program = Program::factory()->create();

        // Package HARUS is_focus_topic=true -- TopicPartGenerator sekarang
        // memfilter khusus package langganan Latihan Fokus, bukan sembarang
        // package untuk program ini.
        Package::factory()->create([
            'program_id' => $program->id,
            'is_focus_topic' => true,
        ]);

        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => $code,
            'name' => $name,
        ]);

        return Topic::create([
            'taxonomy_id' => $taxonomy->id,
            'code' => $code,
            'name' => $name,
        ]);
    }

    protected function makeQuestionsForTopic(Topic $topic, int $count): void
    {
        $bank = QuestionBank::factory()->create([
            'program_id' => $topic->taxonomy->program_id,
            'taxonomy_id' => $topic->taxonomy_id,
            'scoring_type' => 'single_correct',
        ]);

        Question::factory()->count($count)->create([
            'bank_id' => $bank->id,
            'topic_id' => $topic->id,
        ]);
    }

    public function test_generates_part_with_correct_structure(): void
    {
        $topic = $this->makeTopic();
        $this->makeQuestionsForTopic($topic, 10);

        $exam = app(TopicPartGenerator::class)->generateNextPart($topic, 10);

        $this->assertInstanceOf(Exam::class, $exam);
        $this->assertEquals(1, $exam->part_number);
        $this->assertEquals($topic->id, $exam->topic_id);
        $this->assertEquals(10, $exam->questions()->count());
        $this->assertEquals(10, TopicUsedQuestion::where('topic_id', $topic->id)->count());
        $this->assertGreaterThan(0, $exam->sections()->count());
    }

    public function test_second_part_does_not_reuse_questions_from_first_part(): void
    {
        $topic = $this->makeTopic();
        $this->makeQuestionsForTopic($topic, 20);

        $part1 = app(TopicPartGenerator::class)->generateNextPart($topic, 10);
        $part2 = app(TopicPartGenerator::class)->generateNextPart($topic, 10);

        $this->assertEquals(2, $part2->part_number);

        $part1QuestionIds = $part1->questions()->pluck('questions.id')->all();
        $part2QuestionIds = $part2->questions()->pluck('questions.id')->all();

        $this->assertEmpty(array_intersect($part1QuestionIds, $part2QuestionIds));
    }

    public function test_throws_exception_when_not_enough_questions(): void
    {
        $topic = $this->makeTopic();
        $this->makeQuestionsForTopic($topic, 5);

        $this->expectException(\RuntimeException::class);

        app(TopicPartGenerator::class)->generateNextPart($topic, 10);
    }

    public function test_third_part_throws_when_stock_exhausted(): void
    {
        $topic = $this->makeTopic();
        $this->makeQuestionsForTopic($topic, 20);

        app(TopicPartGenerator::class)->generateNextPart($topic, 10);
        app(TopicPartGenerator::class)->generateNextPart($topic, 10);

        $this->expectException(\RuntimeException::class);

        app(TopicPartGenerator::class)->generateNextPart($topic, 10);
    }

    public function test_generates_part_when_stock_exactly_matches_request(): void
    {
        $topic = $this->makeTopic();
        $this->makeQuestionsForTopic($topic, 10);

        $exam = app(TopicPartGenerator::class)->generateNextPart($topic, 10);

        $this->assertEquals(10, $exam->questions()->count());
        $this->assertEquals(10, TopicUsedQuestion::where('topic_id', $topic->id)->count());
    }

    public function test_does_not_use_questions_belonging_to_another_topic(): void
    {
        $topicA = $this->makeTopic('Pilar Negara', 'PIL');
        $topicB = $this->makeTopic('Bahasa Indonesia', 'BIN');

        $this->makeQuestionsForTopic($topicA, 10);
        $this->makeQuestionsForTopic($topicB, 20);

        $examA = app(TopicPartGenerator::class)->generateNextPart($topicA, 10);

        $usedQuestionIds = $examA->questions()->pluck('questions.id')->all();
        $topicBQuestionIds = Question::where('topic_id', $topicB->id)->pluck('id')->all();

        $this->assertEmpty(array_intersect($usedQuestionIds, $topicBQuestionIds));
        $this->assertEquals(0, TopicUsedQuestion::where('topic_id', $topicB->id)->count());
    }

    protected function makeSubjectTopic(string $name = 'Matematika', string $code = 'MTK'): Topic
    {
        // Taxonomy type=subject SENGAJA punya program_id null -- mapel
        // bersifat global lintas Program, beda dengan type=category yang
        // terikat ke satu Program lewat taxonomies.program_id.
        $taxonomy = Taxonomy::create([
            'program_id' => null,
            'type' => 'subject',
            'code' => $code,
            'name' => $name,
        ]);

        return Topic::create([
            'taxonomy_id' => $taxonomy->id,
            'code' => $code,
            'name' => $name,
        ]);
    }

    protected function makeQuestionsForSubjectTopic(Topic $topic, int $count): void
    {
        // Bank soal untuk topic subject tetap butuh program_id (banknya
        // milik satu Program tertentu), walau taxonomy-nya sendiri global.
        $program = Program::factory()->create();

        $bank = QuestionBank::factory()->create([
            'program_id' => $program->id,
            'taxonomy_id' => $topic->taxonomy_id,
            'scoring_type' => 'single_correct',
        ]);

        Question::factory()->count($count)->create([
            'bank_id' => $bank->id,
            'topic_id' => $topic->id,
        ]);
    }

    public function test_part_1_still_generates_for_subject_mode_topic(): void
    {
        // Part 1 selalu free preview, jadi tidak lewat pengecekan
        // program_id -- guard subject-mode tidak boleh menghalangi ini.
        $topic = $this->makeSubjectTopic();
        $this->makeQuestionsForSubjectTopic($topic, 10);

        $exam = app(TopicPartGenerator::class)->generateNextPart($topic, 10);

        $this->assertEquals(1, $exam->part_number);
        $this->assertTrue($exam->is_free_preview);
        $this->assertNull($exam->program_id);
    }

    public function test_throws_when_generating_part_2_for_subject_mode_topic(): void
    {
        // Part 2+ butuh program_id buat cek subscription->coversProgram(),
        // tapi taxonomy subject sengaja punya program_id null -- jadi harus
        // gagal EKSPLISIT di titik generate, bukan silent 403 ke siswa nanti.
        $topic = $this->makeSubjectTopic();
        $this->makeQuestionsForSubjectTopic($topic, 20);

        app(TopicPartGenerator::class)->generateNextPart($topic, 10);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mode subject BELUM didukung');

        app(TopicPartGenerator::class)->generateNextPart($topic, 10);
    }

    public function test_throws_when_only_non_focus_package_exists_for_program(): void
    {
        // Regression test untuk bug yang baru diperbaiki: package biasa
        // (is_focus_topic=false) untuk program yang sama TIDAK boleh
        // dipakai sebagai package langganan Latihan Fokus.
        $program = Program::factory()->create();

        Package::factory()->create([
            'program_id' => $program->id,
            'is_focus_topic' => false,
        ]);

        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'REG',
            'name' => 'Regular Only',
        ]);

        $topic = Topic::create([
            'taxonomy_id' => $taxonomy->id,
            'code' => 'REG',
            'name' => 'Regular Only',
        ]);

        $this->makeQuestionsForTopic($topic, 10);

        $this->expectException(\RuntimeException::class);

        app(TopicPartGenerator::class)->generateNextPart($topic, 10);
    }

    public function test_new_part_attaches_to_focus_topic_package_not_regular_package(): void
    {
        // Regression test: kalau program punya package reguler DAN package
        // focus_topic sekaligus, part baru harus nempel ke package
        // focus_topic-nya saja, bukan ke package reguler.
        $program = Program::factory()->create();

        $regularPackage = Package::factory()->create([
            'program_id' => $program->id,
            'is_focus_topic' => false,
        ]);

        $focusPackage = Package::factory()->create([
            'program_id' => $program->id,
            'is_focus_topic' => true,
        ]);

        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'MIX',
            'name' => 'Mixed Package Program',
        ]);

        $topic = Topic::create([
            'taxonomy_id' => $taxonomy->id,
            'code' => 'MIX',
            'name' => 'Mixed Package Program',
        ]);

        $this->makeQuestionsForTopic($topic, 10);

        $exam = app(TopicPartGenerator::class)->generateNextPart($topic, 10);

        $this->assertTrue($focusPackage->exams()->where('exams.id', $exam->id)->exists());
        $this->assertFalse($regularPackage->exams()->where('exams.id', $exam->id)->exists());
    }
}
