<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSectionScore;
use App\Models\ExamAttemptTopicScore;
use App\Models\ExamSection;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Taxonomy;
use App\Models\Topic;
use App\Services\ExamScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ExamScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ExamScoringService::class);
    }

    /**
     * Taxonomy dan ExamSection tidak pakai HasFactory, jadi dibuat manual
     * lewat ::create() di sini, bukan lewat ::factory().
     *
     * @return array{0: Exam, 1: ExamSection, 2: QuestionBank}
     */
    protected function makeExamWithSection(string $scoringType = 'single_correct', array $sectionAttrs = [], array $bankAttrs = []): array
    {
        $program = Program::factory()->create();

        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'TX-' . uniqid(),
            'name' => 'Taxonomy Test',
        ]);

        $exam = Exam::factory()->create(['program_id' => $program->id]);

        $bank = QuestionBank::factory()->create(array_merge([
            'program_id' => $program->id,
            'taxonomy_id' => $taxonomy->id,
            'scoring_type' => $scoringType,
            'point_correct' => 5,
            'point_wrong' => 0,
        ], $bankAttrs));

        $section = ExamSection::create(array_merge([
            'exam_id' => $exam->id,
            'taxonomy_id' => $taxonomy->id,
            'question_bank_id' => $bank->id,
            'code' => 'SEC-' . uniqid(),
            'name' => 'Section Test',
            'order' => 0,
            'scoring_type' => $scoringType,
            'min_passing_score' => null,
            'max_score' => null,
        ], $sectionAttrs));

        return [$exam, $section, $bank];
    }

    protected function attachQuestionToExam(Exam $exam, ExamSection $section, Question $question): void
    {
        $exam->questions()->syncWithoutDetaching([
            $question->id => ['exam_section_id' => $section->id],
        ]);
    }

    /** @test */
    public function test_pilihan_ganda_benar_dapat_poin_dari_point_correct_bank(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct', [], ['point_correct' => 5]);

        $question = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg']);
        $correctOption = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => true]);
        QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => false]);

        $this->attachQuestionToExam($exam, $section, $question);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option_id' => $correctOption->id,
        ]);

        $result = $this->service->scoreAndPersist($attempt);

        $this->assertSame(5, $result['score']);
        $this->assertSame(1, $result['correct_count']);
        $this->assertFalse($result['has_pending_essay']);
    }

    /**
     * Regression-guard, BUKAN validasi bahwa ini perilaku yang benar:
     * pointWrong() tidak pernah dipanggil di ExamScoringService, jadi
     * jawaban salah SAAT INI selalu 0 poin walau point_wrong diisi -2.
     * Kalau nanti ini diperbaiki jadi memakai pointWrong(), test inilah
     * yang perlu diupdate.
     *
     * @test
     */
    public function test_pilihan_ganda_salah_dapat_nol_poin_bukan_point_wrong(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct', [], [
            'point_correct' => 5,
            'point_wrong' => -2,
        ]);

        $question = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg']);
        QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => true]);
        $wrongOption = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => false]);

        $this->attachQuestionToExam($exam, $section, $question);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option_id' => $wrongOption->id,
        ]);

        $result = $this->service->scoreAndPersist($attempt);

        $this->assertSame(0, $result['score']);
        $this->assertSame(0, $result['correct_count']);
    }

    /** @test */
    public function test_point_correct_override_pada_soal_mengalahkan_point_correct_bank(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct', [], ['point_correct' => 5]);

        $question = Question::factory()->create([
            'bank_id' => $bank->id,
            'type' => 'pg',
            'point_correct_override' => 10,
        ]);
        $correctOption = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => true]);

        $this->attachQuestionToExam($exam, $section, $question);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option_id' => $correctOption->id,
        ]);

        $result = $this->service->scoreAndPersist($attempt);

        $this->assertSame(10, $result['score']);
    }

    /** @test */
    public function test_section_weighted_options_pakai_poin_opsi_langsung_bukan_point_correct(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('weighted_options');

        $question = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg']);
        $option = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => false, 'points' => 3]);

        $this->attachQuestionToExam($exam, $section, $question);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $result = $this->service->scoreAndPersist($attempt);

        $this->assertSame(3, $result['score']);
        // weighted_options: isCorrect ditentukan dari points > 0, bukan is_correct opsi
        $this->assertSame(1, $result['correct_count']);
    }

    /** @test */
    public function test_section_weighted_options_poin_nol_dianggap_tidak_benar(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('weighted_options');

        $question = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg']);
        $option = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => false, 'points' => 0]);

        $this->attachQuestionToExam($exam, $section, $question);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $result = $this->service->scoreAndPersist($attempt);

        $this->assertSame(0, $result['score']);
        $this->assertSame(0, $result['correct_count']);
    }

    /** @test */
    public function test_essay_yang_masih_pending_manual_grading_tidak_ikut_dihitung_dan_menandai_pending(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct');

        $question = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'essay']);

        $this->attachQuestionToExam($exam, $section, $question);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'essay_answer' => 'Jawaban siswa...',
            'needs_manual_grading' => true,
            'is_correct' => false,
        ]);

        $result = $this->service->scoreAndPersist($attempt);

        $this->assertSame(0, $result['score']);
        $this->assertSame(0, $result['correct_count']);
        $this->assertTrue($result['has_pending_essay']);
    }

    /** @test */
    public function test_essay_yang_sudah_dinilai_tutor_ikut_dihitung_pakai_point_correct(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct', [], ['point_correct' => 8]);

        $question = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'essay']);

        $this->attachQuestionToExam($exam, $section, $question);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'essay_answer' => 'Jawaban siswa yang sudah dinilai',
            'needs_manual_grading' => false,
            'is_correct' => true,
        ]);

        $result = $this->service->scoreAndPersist($attempt);

        $this->assertSame(8, $result['score']);
        $this->assertSame(1, $result['correct_count']);
        $this->assertFalse($result['has_pending_essay']);
    }

    /** @test */
    public function test_skor_section_di_cap_ke_max_score_kalau_melebihi(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct', ['max_score' => 6], ['point_correct' => 5]);

        $q1 = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg']);
        $opt1 = QuestionOption::factory()->create(['question_id' => $q1->id, 'is_correct' => true]);
        $q2 = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg']);
        $opt2 = QuestionOption::factory()->create(['question_id' => $q2->id, 'is_correct' => true]);

        $this->attachQuestionToExam($exam, $section, $q1);
        $this->attachQuestionToExam($exam, $section, $q2);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $q1->id, 'selected_option_id' => $opt1->id]);
        ExamAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $q2->id, 'selected_option_id' => $opt2->id]);

        $this->service->scoreAndPersist($attempt);

        $sectionScore = ExamAttemptSectionScore::where('exam_attempt_id', $attempt->id)
            ->where('exam_section_id', $section->id)
            ->first();

        // raw poin 5+5=10, harus di-cap ke max_score 6
        $this->assertSame(6, $sectionScore->raw_score);
        $this->assertSame(2, $sectionScore->correct_count);
    }

    /** @test */
    public function test_passed_threshold_true_kalau_skor_section_mencapai_min_passing_score(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct', ['min_passing_score' => 5], ['point_correct' => 5]);

        $question = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg']);
        $option = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => true]);

        $this->attachQuestionToExam($exam, $section, $question);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'selected_option_id' => $option->id]);

        $this->service->scoreAndPersist($attempt);

        $sectionScore = ExamAttemptSectionScore::where('exam_attempt_id', $attempt->id)
            ->where('exam_section_id', $section->id)
            ->first();

        $this->assertTrue($sectionScore->passed_threshold);
    }

    /** @test */
    public function test_passed_threshold_null_kalau_section_tidak_punya_min_passing_score(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct', ['min_passing_score' => null]);

        $question = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg']);
        $option = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => true]);

        $this->attachQuestionToExam($exam, $section, $question);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'selected_option_id' => $option->id]);

        $this->service->scoreAndPersist($attempt);

        $sectionScore = ExamAttemptSectionScore::where('exam_attempt_id', $attempt->id)
            ->where('exam_section_id', $section->id)
            ->first();

        $this->assertNull($sectionScore->passed_threshold);
    }

    /** @test */
    public function test_soal_tanpa_topic_id_tidak_membuat_baris_topic_score_dan_tidak_error(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct');

        $question = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg', 'topic_id' => null]);
        $option = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => true]);

        $this->attachQuestionToExam($exam, $section, $question);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        ExamAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'selected_option_id' => $option->id]);

        $result = $this->service->scoreAndPersist($attempt);

        $this->assertSame(5, $result['score']);
        $this->assertSame(0, ExamAttemptTopicScore::where('exam_attempt_id', $attempt->id)->count());
    }

    /** @test */
    public function test_topic_score_terakumulasi_benar_dari_beberapa_soal_topik_sama(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct');

        $topic = Topic::create([
            'taxonomy_id' => $bank->taxonomy_id,
            'code' => 'TOPIC-' . uniqid(),
            'name' => 'Topik Test',
        ]);

        $q1 = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg', 'topic_id' => $topic->id]);
        $correctOpt1 = QuestionOption::factory()->create(['question_id' => $q1->id, 'is_correct' => true]);
        $wrongOpt1 = QuestionOption::factory()->create(['question_id' => $q1->id, 'is_correct' => false]);

        $q2 = Question::factory()->create(['bank_id' => $bank->id, 'type' => 'pg', 'topic_id' => $topic->id]);
        $correctOpt2 = QuestionOption::factory()->create(['question_id' => $q2->id, 'is_correct' => true]);

        $this->attachQuestionToExam($exam, $section, $q1);
        $this->attachQuestionToExam($exam, $section, $q2);

        $attempt = ExamAttempt::factory()->create(['exam_id' => $exam->id]);
        // q1 dijawab SALAH, q2 dijawab BENAR
        ExamAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $q1->id, 'selected_option_id' => $wrongOpt1->id]);
        ExamAnswer::factory()->create(['attempt_id' => $attempt->id, 'question_id' => $q2->id, 'selected_option_id' => $correctOpt2->id]);

        $this->service->scoreAndPersist($attempt);

        $topicScore = ExamAttemptTopicScore::where('exam_attempt_id', $attempt->id)
            ->where('topic_id', $topic->id)
            ->first();

        $this->assertNotNull($topicScore);
        $this->assertSame(1, $topicScore->correct_count);
        $this->assertSame(2, $topicScore->total_count);
    }
}
