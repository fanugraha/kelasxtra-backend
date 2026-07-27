<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSectionScore;
use App\Models\ExamSection;
use App\Models\Program;
use App\Models\QuestionBank;
use App\Models\Taxonomy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuestionBankScoringTypeGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Taxonomy dan ExamSection tidak pakai HasFactory, dibuat manual lewat
     * ::create() -- mengikuti pola yang sama seperti ExamScoringServiceTest.
     *
     * @return array{0: Exam, 1: ExamSection, 2: QuestionBank}
     */
    protected function makeExamWithSection(string $scoringType = 'single_correct'): array
    {
        $program = Program::factory()->create();

        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'TX-'.uniqid(),
            'name' => 'Taxonomy Test',
        ]);

        $exam = Exam::factory()->create(['program_id' => $program->id]);

        $bank = QuestionBank::factory()->create([
            'program_id' => $program->id,
            'taxonomy_id' => $taxonomy->id,
            'scoring_type' => $scoringType,
        ]);

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'taxonomy_id' => $taxonomy->id,
            'question_bank_id' => $bank->id,
            'code' => 'SEC-'.uniqid(),
            'name' => 'Section Test',
            'order' => 0,
            'scoring_type' => $scoringType,
            'min_passing_score' => null,
            'max_score' => 100,
        ]);

        return [$exam, $section, $bank];
    }

    public function test_scoring_type_can_be_changed_freely_before_any_attempt_is_graded(): void
    {
        [, $section, $bank] = $this->makeExamWithSection('single_correct');

        $bank->update(['scoring_type' => 'weighted_options']);

        $this->assertSame('weighted_options', $bank->fresh()->scoring_type);
    }

    public function test_scoring_type_change_auto_syncs_to_attached_sections_when_no_attempts_yet(): void
    {
        [, $section, $bank] = $this->makeExamWithSection('single_correct');

        $bank->update(['scoring_type' => 'weighted_options']);

        $this->assertSame('weighted_options', $section->fresh()->scoring_type);
    }

    public function test_scoring_type_change_blocked_once_a_graded_attempt_exists_for_an_attached_section(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct');

        $user = User::factory()->create();
        $attempt = ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
        ]);

        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $attempt->id,
            'exam_section_id' => $section->id,
            'raw_score' => 10,
            'correct_count' => 2,
            'passed_threshold' => true,
        ]);

        $this->expectException(\RuntimeException::class);

        $bank->update(['scoring_type' => 'weighted_options']);
    }

    public function test_scoring_type_change_blocked_leaves_section_untouched(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct');

        $user = User::factory()->create();
        $attempt = ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
        ]);

        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $attempt->id,
            'exam_section_id' => $section->id,
            'raw_score' => 10,
            'correct_count' => 2,
            'passed_threshold' => true,
        ]);

        try {
            $bank->update(['scoring_type' => 'weighted_options']);
        } catch (\RuntimeException) {
            // Diharapkan -- lihat test guard di atas.
        }

        $this->assertSame('single_correct', $bank->fresh()->scoring_type);
        $this->assertSame('single_correct', $section->fresh()->scoring_type);
    }

    public function test_updating_other_fields_without_touching_scoring_type_is_never_blocked(): void
    {
        [$exam, $section, $bank] = $this->makeExamWithSection('single_correct');

        $user = User::factory()->create();
        $attempt = ExamAttempt::factory()->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
        ]);

        ExamAttemptSectionScore::create([
            'exam_attempt_id' => $attempt->id,
            'exam_section_id' => $section->id,
            'raw_score' => 10,
            'correct_count' => 2,
            'passed_threshold' => true,
        ]);

        $bank->update(['title' => 'Judul Baru']);

        $this->assertSame('Judul Baru', $bank->fresh()->title);
    }

    public function test_creating_a_new_bank_with_scoring_type_is_never_blocked(): void
    {
        $program = Program::factory()->create();
        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'TX-'.uniqid(),
            'name' => 'Taxonomy Test',
        ]);

        $bank = QuestionBank::factory()->create([
            'program_id' => $program->id,
            'taxonomy_id' => $taxonomy->id,
            'scoring_type' => 'weighted_options',
        ]);

        $this->assertSame('weighted_options', $bank->fresh()->scoring_type);
    }

    /**
     * P1.5: sebelumnya, section attached-bank baca scoring_type dari
     * kolomnya sendiri, dijaga sinkron lewat write-side hook. Sekarang
     * bacanya LIVE dari bank -- test ini membuktikannya dengan sengaja
     * bikin kolom exam_sections.scoring_type basi lewat query mentah (skip
     * semua model event), lalu pastikan accessor tetap balikin nilai bank
     * yang sebenarnya, bukan kolom yang basi itu.
     */
    public function test_attached_section_scoring_type_reads_live_from_bank_ignoring_stale_column(): void
    {
        [, $section, $bank] = $this->makeExamWithSection('single_correct');

        // Simulasi kolom basi di DB tanpa lewat model sama sekali -- kalau
        // accessor masih bergantung ke kolom ini, test ini akan gagal.
        DB::table('exam_sections')->where('id', $section->id)->update(['scoring_type' => 'weighted_options']);

        $this->assertSame('single_correct', $section->fresh()->scoring_type);
    }

    /**
     * Section Latihan Topik (question_bank_id NULL, lihat TopicPartGenerator)
     * tidak punya bank sumber -- untuk kasus ini kolomnya sendiri TETAP jadi
     * satu-satunya sumber kebenaran, tidak boleh berubah jadi null/error.
     */
    public function test_standalone_section_without_bank_reads_its_own_column(): void
    {
        $exam = Exam::factory()->create();

        $section = ExamSection::create([
            'exam_id' => $exam->id,
            'taxonomy_id' => null,
            'question_bank_id' => null,
            'code' => 'SEC-'.uniqid(),
            'name' => 'Latihan Topik Section',
            'order' => 0,
            'scoring_type' => 'single_correct',
            'min_passing_score' => null,
            'max_score' => 100,
        ]);

        $this->assertSame('single_correct', $section->fresh()->scoring_type);
    }
}
