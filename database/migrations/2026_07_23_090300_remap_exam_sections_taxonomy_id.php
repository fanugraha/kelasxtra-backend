<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('exam_sections')->orderBy('id')->chunk(200, function ($sections) {
            foreach ($sections as $section) {
                $exam = DB::table('exams')->find($section->exam_id);
                if (! $exam) {
                    continue;
                }

                $program = DB::table('programs')->find($exam->program_id);
                if (! $program) {
                    continue;
                }

                $usesSubjectMode = $program->question_grouping_mode === 'subject';

                $newTaxonomy = $usesSubjectMode
                    ? DB::table('taxonomies')
                        ->where('type', 'subject')
                        ->where('legacy_subject_id', $section->legacy_taxonomy_id)
                        ->first()
                    : DB::table('taxonomies')
                        ->where('type', 'category')
                        ->where('legacy_category_id', $section->legacy_taxonomy_id)
                        ->first();

                if ($newTaxonomy) {
                    DB::table('exam_sections')
                        ->where('id', $section->id)
                        ->update(['taxonomy_id' => $newTaxonomy->id]);
                } else {
                    logger()->warning('taxonomy remap: no match found', [
                        'exam_section_id' => $section->id,
                        'legacy_taxonomy_id' => $section->legacy_taxonomy_id,
                        'program_mode' => $program->question_grouping_mode,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::statement('UPDATE exam_sections SET taxonomy_id = legacy_taxonomy_id');
    }
};
