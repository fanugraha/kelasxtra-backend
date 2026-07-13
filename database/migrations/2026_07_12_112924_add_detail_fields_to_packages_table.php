<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->json('features')->nullable()->after('description');
            $table->json('materi')->nullable()->after('features');
            $table->string('banner_image_url')->nullable()->after('materi');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['features', 'materi', 'banner_image_url']);
        });
    }
};
