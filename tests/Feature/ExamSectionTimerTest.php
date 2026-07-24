<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Program;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Taxonomy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamSectionTimerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function makeBankWithQuestion(Program $program, string $code): array
    {
        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'subject',
            'code' => $code,
            'name' => "Section {$code}",
        ]);

        $bank = QuestionBank::factory()->create([
            'program_id' => $program->id,
            'taxonomy_id' => $taxonomy->id,
        ]);

        $question = Question::factory()->create(['bank_id' => $bank->id]);
        $correctOption = QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => true, 'points' => 5]);
        QuestionOption::factory()->create(['question_id' => $question->id, 'is_correct' => false, 'points' => 0]);

        return ['bank' => $bank, 'question_id' => $question->id, 'option_id' => $correctOption->id];
    }

    /**
     * @param array $sectionsSpec list of ['duration_minutes' => int|null]
     */
    protected function makeExamWithTimedSections(array $sectionsSpec, int $examDurationMinutes = 120): array
    {
        $program = Program::factory()->create();

        $exam = Exam::factory()->create([
            'program_id' => $program->id,
            'uses_section_timers' => true,
            'is_free_preview' => true,
            'duration_minutes' => $examDurationMinutes,
        ]);

        $sections = [];
        foreach ($sectionsSpec as $i => $spec) {
            $made = $this->makeBankWithQuestion($program, 'SEC'.($i + 1));
            $section = $exam->attachBank($made['bank'], array_merge(['order' => $i + 1], $spec));

            $sections[] = [
                'section' => $section,
                'bank' => $made['bank'],
                'question_id' => $made['question_id'],
                'option_id' => $made['option_id'],
            ];
        }

        return ['exam' => $exam->fresh(), 'sections' => $sections];
    }

    protected function startAttempt(Exam $exam, $bankId, User $user): ExamAttempt
    {
        Sanctum::actingAs($user);

        $this->postJson('/api/exams/start', [
            'exam_id' => $exam->id,
            'bank_id' => $bankId,
        ])->assertStatus(201);

        return ExamAttempt::where('user_id', $user->id)
            ->where('exam_id', $exam->id)
            ->where('bank_id', $bankId)
            ->firstOrFail();
    }

    public function test_section_otomatis_maju_ke_section_berikutnya_saat_waktu_habis(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $data = $this->makeExamWithTimedSections([
            ['duration_minutes' => 10],
            ['duration_minutes' => 10],
        ]);
        $exam = $data['exam'];
        $section1 = $data['sections'][0]['section'];
        $section2 = $data['sections'][1]['section'];
        $bank1 = $data['sections'][0]['bank'];

        $user = User::factory()->create();
        $attempt = $this->startAttempt($exam, $bank1->id, $user);

        $this->assertSame($section1->id, $attempt->current_section_id);

        // 11 menit kemudian: deadline section 1 (08:10) sudah lewat.
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:11:00'));

        $this->getJson("/api/exam-attempts/{$attempt->id}")->assertOk();

        $attempt->refresh();

        $this->assertSame($section2->id, $attempt->current_section_id);
        $this->assertSame('in_progress', $attempt->status);
        // section_started_at baru = deadline section 1 (08:10), BUKAN now() (08:11) --
        // siswa tidak boleh "untung" waktu tambahan gara-gara telat cek.
        $this->assertTrue($attempt->section_started_at->equalTo(Carbon::parse('2026-01-01 08:10:00')));
    }

    public function test_section_terakhir_habis_waktu_menutup_seluruh_attempt_meski_dilewati_sekaligus(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $data = $this->makeExamWithTimedSections([
            ['duration_minutes' => 10],
            ['duration_minutes' => 10],
        ]);
        $exam = $data['exam'];
        $bank1 = $data['sections'][0]['bank'];

        $user = User::factory()->create();
        $attempt = $this->startAttempt($exam, $bank1->id, $user);

        // Siswa tidak membuka halaman sama sekali sampai KEDUA section lewat
        // (deadline section 2 = 08:20). Loop di checkAndAdvanceSection() harus
        // memajukan section 1 -> section 2 -> lalu menutup attempt sekaligus
        // dalam satu request, bukan berhenti di section 2 yang juga sudah lewat.
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:25:00'));

        $this->getJson("/api/exam-attempts/{$attempt->id}")->assertOk();

        $attempt->refresh();

        $this->assertSame('graded', $attempt->status);
        $this->assertNotNull($attempt->finished_at);
    }

    public function test_submit_jawaban_ditolak_untuk_soal_section_yang_sudah_tidak_aktif(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $data = $this->makeExamWithTimedSections([
            ['duration_minutes' => 10],
            ['duration_minutes' => 10],
        ]);
        $exam = $data['exam'];
        $bank1 = $data['sections'][0]['bank'];
        $section1QuestionId = $data['sections'][0]['question_id'];
        $section1OptionId = $data['sections'][0]['option_id'];

        $user = User::factory()->create();
        $attempt = $this->startAttempt($exam, $bank1->id, $user);

        // Lewati deadline section 1 supaya attempt otomatis maju ke section 2.
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:11:00'));

        $response = $this->postJson("/api/exam-attempts/{$attempt->id}/answer", [
            'question_id' => $section1QuestionId,
            'selected_option_id' => $section1OptionId,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Soal ini bukan bagian dari bagian ujian yang sedang aktif.');
    }

    public function test_submit_jawaban_untuk_soal_section_aktif_berhasil(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $data = $this->makeExamWithTimedSections([
            ['duration_minutes' => 10],
            ['duration_minutes' => 10],
        ]);
        $exam = $data['exam'];
        $bank1 = $data['sections'][0]['bank'];
        $section1QuestionId = $data['sections'][0]['question_id'];
        $section1OptionId = $data['sections'][0]['option_id'];

        $user = User::factory()->create();
        $attempt = $this->startAttempt($exam, $bank1->id, $user);

        $response = $this->postJson("/api/exam-attempts/{$attempt->id}/answer", [
            'question_id' => $section1QuestionId,
            'selected_option_id' => $section1OptionId,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Jawaban tersimpan.');
    }

    public function test_section_tanpa_durasi_tidak_dibatasi_waktu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $data = $this->makeExamWithTimedSections([
            ['duration_minutes' => null],
            ['duration_minutes' => 10],
        ]);
        $exam = $data['exam'];
        $section1 = $data['sections'][0]['section'];
        $bank1 = $data['sections'][0]['bank'];

        $user = User::factory()->create();
        $attempt = $this->startAttempt($exam, $bank1->id, $user);

        // 100 menit berlalu (masih dalam batas duration_minutes total exam = 120).
        Carbon::setTestNow(Carbon::parse('2026-01-01 09:40:00'));

        $this->getJson("/api/exam-attempts/{$attempt->id}")->assertOk();

        $attempt->refresh();

        $this->assertSame($section1->id, $attempt->current_section_id);
        $this->assertSame('in_progress', $attempt->status);
    }

    public function test_attempt_lama_tanpa_current_section_id_otomatis_diperbaiki_ke_section_pertama(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        $data = $this->makeExamWithTimedSections([
            ['duration_minutes' => 10],
        ]);
        $exam = $data['exam'];
        $section1 = $data['sections'][0]['section'];
        $bank1 = $data['sections'][0]['bank'];

        $user = User::factory()->create();

        // Simulasi attempt lama sebelum fitur timer per-section ada.
        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'bank_id' => $bank1->id,
            'question_order' => ['questions' => [], 'options' => []],
            'started_at' => now(),
            'status' => 'in_progress',
            'tab_switch_count' => 0,
            'current_section_id' => null,
            'section_started_at' => null,
        ]);

        Sanctum::actingAs($user);
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:02:00'));

        $this->getJson("/api/exam-attempts/{$attempt->id}")->assertOk();

        $attempt->refresh();

        $this->assertSame($section1->id, $attempt->current_section_id);
        $this->assertNotNull($attempt->section_started_at);
        $this->assertTrue($attempt->section_started_at->equalTo(Carbon::parse('2026-01-01 08:02:00')));
    }

    public function test_deadline_global_exam_tetap_menutup_attempt_walau_section_belum_habis(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 08:00:00'));

        // Section durasinya sangat panjang (tidak akan pernah habis duluan),
        // tapi durasi total exam sengaja pendek -- timer global harus tetap
        // menutup attempt meski timer section belum habis sama sekali.
        $data = $this->makeExamWithTimedSections([
            ['duration_minutes' => 1000],
        ], examDurationMinutes: 5);
        $exam = $data['exam'];
        $section1 = $data['sections'][0]['section'];
        $bank1 = $data['sections'][0]['bank'];

        $user = User::factory()->create();
        $attempt = $this->startAttempt($exam, $bank1->id, $user);

        Carbon::setTestNow(Carbon::parse('2026-01-01 08:06:00'));

        $this->getJson("/api/exam-attempts/{$attempt->id}")->assertOk();

        $attempt->refresh();

        $this->assertSame('graded', $attempt->status);
        $this->assertNotNull($attempt->finished_at);
        // Section aktif tidak berubah -- yang menutup attempt adalah timer
        // global, bukan mekanisme advance section.
        $this->assertSame($section1->id, $attempt->current_section_id);
    }
}
