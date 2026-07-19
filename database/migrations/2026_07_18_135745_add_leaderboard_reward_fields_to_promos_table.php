<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->foreignId('restricted_to_user_id')
                ->nullable()
                ->after('code')
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('source', ['manual', 'leaderboard_reward'])
                ->default('manual')
                ->after('restricted_to_user_id');

            $table->unsignedBigInteger('leaderboard_entry_id')
                ->nullable()
                ->after('source');

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropForeign(['restricted_to_user_id']);
            $table->dropColumn(['restricted_to_user_id', 'source', 'leaderboard_entry_id']);
        });
    }
};
