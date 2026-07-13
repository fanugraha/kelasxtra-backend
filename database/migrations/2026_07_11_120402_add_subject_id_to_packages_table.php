<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('packages', function (Blueprint $table) {
        $table->foreignId('subject_id')->nullable()->after('program_id')->constrained('subjects')->cascadeOnDelete();
        $table->foreignId('program_id')->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('packages', function (Blueprint $table) {
        $table->dropConstrainedForeignId('subject_id');
        $table->foreignId('program_id')->nullable(false)->change();
    });
}
};
