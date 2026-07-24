<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()
                ->constrained('programs')->nullOnDelete();
            $table->enum('type', ['category', 'subject']);
            $table->string('code')->nullable();
            $table->string('name');
            $table->unsignedInteger('passing_grade')->nullable();
            $table->boolean('requires_passage')->nullable();
            $table->unsignedBigInteger('legacy_category_id')->nullable();
            $table->unsignedBigInteger('legacy_subject_id')->nullable();
            $table->timestamps();
            $table->index(['type', 'program_id']);
            $table->index('legacy_category_id');
            $table->index('legacy_subject_id');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreign('focus_taxonomy_id')
                ->references('id')->on('taxonomies')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropForeign(['focus_taxonomy_id']);
        });
        Schema::dropIfExists('taxonomies');
    }
};
