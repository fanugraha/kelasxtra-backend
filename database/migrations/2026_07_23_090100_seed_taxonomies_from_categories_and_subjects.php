<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('categories')->orderBy('id')->chunk(200, function ($categories) use ($now) {
            $rows = $categories->map(fn ($category) => [
                'program_id'        => $category->program_id,
                'type'              => 'category',
                'code'              => $category->code,
                'name'              => $category->name,
                'passing_grade'     => $category->passing_grade,
                'requires_passage'  => $category->requires_passage,
                'legacy_category_id' => $category->id,
                'legacy_subject_id'  => null,
                'created_at'        => $category->created_at ?? $now,
                'updated_at'        => $category->updated_at ?? $now,
            ])->toArray();

            DB::table('taxonomies')->insert($rows);
        });

        DB::table('subjects')->orderBy('id')->chunk(200, function ($subjects) use ($now) {
            $rows = $subjects->map(fn ($subject) => [
                'program_id'        => null,
                'type'              => 'subject',
                'code'              => null,
                'name'              => $subject->name,
                'passing_grade'     => null,
                'requires_passage'  => null,
                'legacy_category_id' => null,
                'legacy_subject_id'  => $subject->id,
                'created_at'        => $subject->created_at ?? $now,
                'updated_at'        => $subject->updated_at ?? $now,
            ])->toArray();

            DB::table('taxonomies')->insert($rows);
        });
    }

    public function down(): void
    {
        DB::table('taxonomies')->truncate();
    }
};
