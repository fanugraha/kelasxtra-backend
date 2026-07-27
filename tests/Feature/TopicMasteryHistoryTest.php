<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Package;
use App\Models\Program;
use App\Models\Taxonomy;
use App\Models\Topic;
use App\Models\TopicMasterySnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TopicMasteryHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Program, 1: Topic}
     */
    protected function setupProgramWithTopic(): array
    {
        $program = Program::factory()->create();

        $taxonomy = Taxonomy::create([
            'program_id' => $program->id,
            'type' => 'category',
            'code' => 'TWK',
            'name' => 'Tes Wawasan Kebangsaan',
        ]);

        $topic = Topic::create(['taxonomy_id' => $taxonomy->id, 'code' => 'T1', 'name' => 'Pancasila']);

        return [$program, $topic];
    }

    protected function grantFullAccess(User $user, Program $program): void
    {
        $package = Package::factory()->create(['program_id' => $program->id]);

        Enrollment::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(30),
        ]);
    }

    public function test_topic_id_wajib_diisi_dan_harus_ada(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/me/topic-mastery-history')->assertStatus(422);
        $this->getJson('/api/me/topic-mastery-history?topic_id=99999')->assertStatus(422);
    }

    public function test_tanpa_akses_penuh_periods_kosong_dan_upgrade_cta_muncul(): void
    {
        [$program, $topic] = $this->setupProgramWithTopic();
        $user = User::factory()->create();

        TopicMasterySnapshot::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'period' => '2026-W20',
            'correct_count' => 7,
            'total_count' => 10,
            'percentage' => 70,
            'trend' => null,
            'computed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/topic-mastery-history?topic_id={$topic->id}");

        $response->assertOk()
            ->assertJsonPath('access.full', false)
            ->assertJsonPath('periods', [])
            ->assertJsonPath('access.upgrade_cta.action_link', "/app/packages?program_id={$program->id}");
    }

    public function test_dengan_akses_penuh_periods_terurut_lama_ke_baru(): void
    {
        [$program, $topic] = $this->setupProgramWithTopic();
        $user = User::factory()->create();
        $this->grantFullAccess($user, $program);

        // Sengaja dibuat tidak berurutan supaya membuktikan response-nya
        // di-sort ulang (lama -> baru), bukan cuma ikut urutan insert.
        TopicMasterySnapshot::create([
            'user_id' => $user->id, 'topic_id' => $topic->id, 'period' => '2026-W22',
            'correct_count' => 8, 'total_count' => 10, 'percentage' => 80, 'trend' => 'up', 'computed_at' => now(),
        ]);
        TopicMasterySnapshot::create([
            'user_id' => $user->id, 'topic_id' => $topic->id, 'period' => '2026-W20',
            'correct_count' => 4, 'total_count' => 10, 'percentage' => 40, 'trend' => null, 'computed_at' => now(),
        ]);
        TopicMasterySnapshot::create([
            'user_id' => $user->id, 'topic_id' => $topic->id, 'period' => '2026-W21',
            'correct_count' => 6, 'total_count' => 10, 'percentage' => 60, 'trend' => 'up', 'computed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/topic-mastery-history?topic_id={$topic->id}");

        $response->assertOk()
            ->assertJsonPath('access.full', true)
            ->assertJsonPath('topic.id', $topic->id)
            ->assertJsonCount(3, 'periods')
            ->assertJsonPath('periods.0.period', '2026-W20')
            ->assertJsonPath('periods.1.period', '2026-W21')
            ->assertJsonPath('periods.2.period', '2026-W22')
            ->assertJsonPath('periods.2.percentage', 80);
    }

    public function test_periods_query_param_membatasi_jumlah_periode_terbaru(): void
    {
        [$program, $topic] = $this->setupProgramWithTopic();
        $user = User::factory()->create();
        $this->grantFullAccess($user, $program);

        foreach (['2026-W18', '2026-W19', '2026-W20'] as $i => $period) {
            TopicMasterySnapshot::create([
                'user_id' => $user->id, 'topic_id' => $topic->id, 'period' => $period,
                'correct_count' => 5, 'total_count' => 10, 'percentage' => 50 + $i, 'trend' => null,
                'computed_at' => now(),
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/topic-mastery-history?topic_id={$topic->id}&periods=2");

        // Ambil 2 TERBARU (W19, W20), bukan 2 pertama dari urutan insert.
        $response->assertOk()
            ->assertJsonCount(2, 'periods')
            ->assertJsonPath('periods.0.period', '2026-W19')
            ->assertJsonPath('periods.1.period', '2026-W20');
    }

    public function test_topik_tanpa_snapshot_sama_sekali_mengembalikan_periods_kosong(): void
    {
        [$program, $topic] = $this->setupProgramWithTopic();
        $user = User::factory()->create();
        $this->grantFullAccess($user, $program);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/topic-mastery-history?topic_id={$topic->id}");

        $response->assertOk()
            ->assertJsonPath('access.full', true)
            ->assertJsonPath('periods', []);
    }

    public function test_riwayat_milik_user_lain_tidak_ikut_tercampur(): void
    {
        [$program, $topic] = $this->setupProgramWithTopic();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->grantFullAccess($user, $program);

        TopicMasterySnapshot::create([
            'user_id' => $otherUser->id, 'topic_id' => $topic->id, 'period' => '2026-W20',
            'correct_count' => 9, 'total_count' => 10, 'percentage' => 90, 'trend' => null, 'computed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/me/topic-mastery-history?topic_id={$topic->id}");

        $response->assertOk()->assertJsonPath('periods', []);
    }
}
