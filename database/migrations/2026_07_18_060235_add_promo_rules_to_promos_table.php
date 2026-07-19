<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            // Teks S&K bebas (ini yang ketinggalan dari pembahasan sebelumnya)
            $table->text('terms')->nullable()->after('description');

            // --- Wajib ---
            $table->unsignedInteger('total_quota')->nullable()->after('terms');
            $table->boolean('new_user_only')->default(false)->after('total_quota');
            $table->unsignedInteger('usage_limit_per_user')->nullable()->after('new_user_only');

            // --- Nice to have ---
            $table->unsignedInteger('max_discount_amount')->nullable()->after('usage_limit_per_user');
            $table->timestamp('valid_from')->nullable()->after('max_discount_amount');
            $table->boolean('is_active')->default(true)->after('valid_from');
            $table->foreignId('applicable_package_id')->nullable()
                ->after('is_active')
                ->constrained('packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropForeign(['applicable_package_id']);
            $table->dropColumn([
                'terms',
                'total_quota',
                'new_user_only',
                'usage_limit_per_user',
                'max_discount_amount',
                'valid_from',
                'is_active',
                'applicable_package_id',
            ]);
        });
    }
};