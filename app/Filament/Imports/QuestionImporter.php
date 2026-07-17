<?php

namespace App\Filament\Imports;

use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionPassage;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Support\Number;

class QuestionImporter extends Importer
{
    protected static ?string $model = Question::class;

    /**
     * Nilai yang dikenali sebagai TRUE/FALSE untuk kolom correct_X.
     * Kalau isi kolom tidak masuk salah satu list ini (dan tidak kosong),
     * baris ditolak -- supaya salah ketik tidak diam-diam dianggap "salah".
     */
    protected const TRUE_VALUES = ['1', 'true', 'ya', 'yes', 'benar'];
    protected const FALSE_VALUES = ['0', 'false', 'tidak', 'no', 'salah'];

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('category')
                ->label('Kategori (kode)')
                ->requiredMapping()
                ->relationship(resolveUsing: 'code')
                ->rules(['required'])
                ->example('REA'),

            ImportColumn::make('passage_text')
                ->label('Teks Passage (opsional, isi sama = 1 passage)')
                ->fillRecordUsing(fn () => null)
                ->example('Photosynthesis is the process by which green plants...'),

            ImportColumn::make('question_text')
                ->label('Pertanyaan')
                ->requiredMapping()
                ->rules(['required'])
                ->example('According to the passage, what do green plants produce?'),

            ImportColumn::make('type')
                ->label('Tipe (pg/essay)')
                ->requiredMapping()
                ->rules(['required', 'in:pg,essay'])
                ->example('pg'),

            ImportColumn::make('difficulty')
                ->label('Kesulitan (mudah/sedang/sulit)')
                ->rules(['nullable', 'in:mudah,sedang,sulit'])
                ->example('sedang'),

            ImportColumn::make('media_type')
                ->label('Jenis Media')
                ->rules(['nullable', 'in:none,image,audio'])
                ->castStateUsing(fn (?string $state) => blank($state) ? 'none' : $state)
                ->example('none'),

            ImportColumn::make('media_url')
                ->label('URL Media (opsional)')
                ->rules(['nullable', 'max:255']),

            ImportColumn::make('explanation')
                ->label('Pembahasan (opsional)')
                ->rules(['nullable'])
                ->example('Karena proses fotosintesis menghasilkan oksigen dan glukosa dari CO2 + air.'),

            ImportColumn::make('points')
                ->label('Poin Soal Ini di Exam (opsional)')
                ->rules(['nullable', 'numeric'])
                ->fillRecordUsing(fn () => null)
                ->example('5'),

            ImportColumn::make('option_1')->label('Opsi 1')->fillRecordUsing(fn () => null)->example('Oxygen and glucose'),
            ImportColumn::make('correct_1')->label('Opsi 1 Benar? (1/0, kosongkan utk TKP)')->fillRecordUsing(fn () => null)->example('1'),
            ImportColumn::make('points_1')->label('Opsi 1 Poin (khusus TKP, 1-5)')->fillRecordUsing(fn () => null)->example('5'),

            ImportColumn::make('option_2')->label('Opsi 2')->fillRecordUsing(fn () => null)->example('Nitrogen and water'),
            ImportColumn::make('correct_2')->label('Opsi 2 Benar? (1/0, kosongkan utk TKP)')->fillRecordUsing(fn () => null)->example('0'),
            ImportColumn::make('points_2')->label('Opsi 2 Poin (khusus TKP, 1-5)')->fillRecordUsing(fn () => null),

            ImportColumn::make('option_3')->label('Opsi 3')->fillRecordUsing(fn () => null),
            ImportColumn::make('correct_3')->label('Opsi 3 Benar? (1/0, kosongkan utk TKP)')->fillRecordUsing(fn () => null),
            ImportColumn::make('points_3')->label('Opsi 3 Poin (khusus TKP, 1-5)')->fillRecordUsing(fn () => null),

            ImportColumn::make('option_4')->label('Opsi 4')->fillRecordUsing(fn () => null),
            ImportColumn::make('correct_4')->label('Opsi 4 Benar? (1/0, kosongkan utk TKP)')->fillRecordUsing(fn () => null),
            ImportColumn::make('points_4')->label('Opsi 4 Poin (khusus TKP, 1-5)')->fillRecordUsing(fn () => null),

            ImportColumn::make('option_5')->label('Opsi 5')->fillRecordUsing(fn () => null),
            ImportColumn::make('correct_5')->label('Opsi 5 Benar? (1/0, kosongkan utk TKP)')->fillRecordUsing(fn () => null),
            ImportColumn::make('points_5')->label('Opsi 5 Poin (khusus TKP, 1-5)')->fillRecordUsing(fn () => null),
        ];
    }

    /**
     * Field-field yang muncul di modal SEBELUM import dijalankan.
     * source_bank_id: dipakai kalau import dipicu dari halaman Exam (bank_id belum pasti).
     * assign_exam_id / assign_exam_section_id: dipakai kalau import dipicu dari halaman
     * Bank Soal dan user MAU sekalian assign ke exam+section tertentu (opsional).
     * Kalau import dipicu dari halaman Exam, exam_id sudah otomatis fix lewat context
     * (lihat ExamResource\RelationManagers\QuestionsRelationManager), field ini tidak akan
     * dipakai/ditimpa karena context menang.
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('source_bank_id')
                ->label('Bank Soal (sumber)')
                ->options(fn () => QuestionBank::pluck('title', 'id'))
                ->searchable()
                ->helperText('Wajib diisi kalau import ini dijalankan dari halaman Exam.'),

            Select::make('assign_exam_id')
                ->label('Assign ke Exam (opsional)')
                ->options(fn () => Exam::pluck('title', 'id'))
                ->searchable()
                ->live()
                ->helperText('Kosongkan kalau cuma mau isi bank soal tanpa langsung merakit jadi TO.'),

            Select::make('assign_exam_section_id')
                ->label('Bagian Ujian (opsional)')
                ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                    $examId = $get('assign_exam_id');

                    if (blank($examId)) {
                        return [];
                    }

                    return ExamSection::where('exam_id', $examId)->pluck('name', 'id');
                })
                ->searchable()
                ->helperText('Kosongkan kalau exam ini tidak memakai pembagian section, atau kalau tidak assign ke exam.'),
        ];
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    protected function getBankId(): ?int
    {
        return $this->options['bank_id'] ?? $this->options['source_bank_id'] ?? null;
    }

    protected function getExamId(): ?int
    {
        return $this->options['exam_id'] ?? $this->options['assign_exam_id'] ?? null;
    }

    protected function getExamSectionId(): ?int
    {
        $explicit = $this->options['exam_section_id'] ?? $this->options['assign_exam_section_id'] ?? null;

        if ($explicit) {
            return (int) $explicit;
        }

        // Auto-match: cari ExamSection di exam ini yang category_id-nya
        // sama dengan category_id soal yang baru dibuat.
        $examId = $this->getExamId();
        $categoryId = $this->record->category_id ?? null;

        if (blank($examId) || blank($categoryId)) {
            return null;
        }

        return \App\Models\ExamSection::where('exam_id', $examId)
            ->where('category_id', $categoryId)
            ->value('id');
    }

    public function resolveRecord(): Question
    {
        $bankId = $this->getBankId();

        $passageId = null;
        $passageText = trim((string) ($this->data['passage_text'] ?? ''));

        if ($passageText !== '') {
            $passage = QuestionPassage::firstOrCreate([
                'question_bank_id' => $bankId,
                'passage_text' => $passageText,
            ]);

            $passageId = $passage->id;
        }

        return new Question([
            'bank_id' => $bankId,
            'passage_id' => $passageId,
        ]);
    }

    protected function beforeCreate(): void
    {
        if (empty($this->getBankId())) {
            throw new RowImportFailedException(
                'Bank Soal tidak diketahui. Pilih "Bank Soal (sumber)" di form sebelum import, atau jalankan import ini dari dalam halaman Bank Soal.'
            );
        }

        // Validasi opsi jawaban dilakukan SEBELUM record disimpan, supaya baris
        // yang gagal validasi tidak sempat membuat row Question yatim tanpa opsi.
        if ($this->data['type'] === 'pg') {
            $this->validateOptionsOrFail();
        }
    }

    /**
     * Parse nilai correct_X secara ketat. Mengembalikan:
     * - true  : kalau nilai ada di TRUE_VALUES
     * - false : kalau nilai kosong ATAU ada di FALSE_VALUES
     * - null  : kalau nilai TERISI tapi tidak dikenali sama sekali (baris harus ditolak)
     */
    protected function parseCorrectFlag(string $rawValue): ?bool
    {
        $normalized = strtolower(trim($rawValue));

        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, self::TRUE_VALUES, true)) {
            return true;
        }

        if (in_array($normalized, self::FALSE_VALUES, true)) {
            return false;
        }

        return null;
    }

    /**
     * Cek kelengkapan & konsistensi opsi jawaban SEBELUM soal disimpan.
     * Aturan:
     * 1. Minimal 2 opsi (option_text) harus terisi.
     * 2. Kalau ADA kolom points_X yang diisi untuk opsi manapun (mode TKP/bobot),
     *    tidak wajib ada opsi "benar" -- karena scoring-nya berbasis poin per opsi,
     *    bukan benar/salah.
     * 3. Kalau TIDAK ada points_X sama sekali (mode PG biasa), wajib ada MINIMAL
     *    1 opsi yang ditandai benar (correct_X terisi valid true).
     * 4. correct_X yang terisi tapi nilainya tidak dikenali (bukan 1/0/ya/tidak/dst)
     *    membuat baris ditolak, supaya salah ketik tidak lolos diam-diam.
     */
    protected function validateOptionsOrFail(): void
    {
        $filledCount = 0;
        $hasAnyCorrect = false;
        $hasAnyPoints = false;

        for ($i = 1; $i <= 5; $i++) {
            $optionText = trim((string) ($this->data["option_{$i}"] ?? ''));

            if ($optionText === '') {
                continue;
            }

            $filledCount++;

            $correctRaw = (string) ($this->data["correct_{$i}"] ?? '');
            $pointsRaw = trim((string) ($this->data["points_{$i}"] ?? ''));

            if ($pointsRaw !== '') {
                $hasAnyPoints = true;
            }

            $parsedCorrect = $this->parseCorrectFlag($correctRaw);

            if ($parsedCorrect === null) {
                throw new RowImportFailedException(
                    "Nilai kolom correct_{$i} = \"{$correctRaw}\" tidak dikenali. Gunakan 1/0, ya/tidak, atau benar/salah. Kosongkan kolom ini kalau soal ini pakai mode poin (TKP)."
                );
            }

            if ($parsedCorrect === true) {
                $hasAnyCorrect = true;
            }
        }

        if ($filledCount < 2) {
            throw new RowImportFailedException(
                "Soal Pilihan Ganda wajib punya minimal 2 opsi jawaban terisi (option_1, option_2, dst). Baris ini hanya punya {$filledCount} opsi."
            );
        }

        if (!$hasAnyPoints && !$hasAnyCorrect) {
            throw new RowImportFailedException(
                'Tidak ada opsi yang ditandai benar (correct_X). Soal PG biasa wajib punya minimal 1 opsi benar, atau isi kolom points_X kalau soal ini pakai mode bobot poin (TKP).'
            );
        }
    }

    protected function afterCreate(): void
    {
        if ($this->record->type === 'pg') {
            for ($i = 1; $i <= 5; $i++) {
                $optionText = trim((string) ($this->data["option_{$i}"] ?? ''));

                if ($optionText === '') {
                    continue;
                }

                $isCorrect = $this->parseCorrectFlag((string) ($this->data["correct_{$i}"] ?? '')) === true;

                $pointsRaw = trim((string) ($this->data["points_{$i}"] ?? ''));
                $points = $pointsRaw !== '' ? (int) $pointsRaw : 0;

                $this->record->options()->create([
                    'option_text' => $optionText,
                    'is_correct' => $isCorrect,
                    'points' => $points,
                ]);
            }
        }

        $examId = $this->getExamId();

        if (blank($examId)) {
            return;
        }

        $points = trim((string) ($this->data['points'] ?? ''));

        $this->record->exam()->attach($examId, [
            'exam_section_id' => $this->getExamSectionId(),
            'points' => $points !== '' ? (int) $points : 1,
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import soal selesai, ' . Number::format($import->successful_rows) . ' ' . str('baris')->plural($import->successful_rows) . ' berhasil diimport.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('baris')->plural($failedRowsCount) . ' gagal.';
        }

        return $body;
    }
}
