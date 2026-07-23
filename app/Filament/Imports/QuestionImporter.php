<?php

namespace App\Filament\Imports;

use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionPassage;
use App\Models\Topic;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Support\Number;

/**
 * Import soal SELALU untuk 1 Bank Soal (bank_id datang dari options() saat
 * importer dipicu dari dalam halaman Bank Soal -- lihat
 * QuestionBankResource/RelationManagers/QuestionsRelationManager). Kategori
 * & aturan poin soal ini sudah ditentukan di level Bank Soal itu sendiri,
 * jadi CSV tidak perlu (dan tidak boleh) punya kolom kategori/poin-exam lagi.
 * Assignment ke Exam dilakukan terpisah lewat Exam::attachBank() di tab
 * Bagian Ujian milik Exam, bukan di sini.
 */
class QuestionImporter extends Importer
{
    protected static ?string $model = Question::class;

    protected const TRUE_VALUES = ['1', 'true', 'ya', 'yes', 'benar'];
    protected const FALSE_VALUES = ['0', 'false', 'tidak', 'no', 'salah'];

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('passage_text')
                ->label('Teks Passage (opsional, isi sama = 1 passage)')
                ->fillRecordUsing(fn () => null)
                ->example('Photosynthesis is the process by which green plants...'),

            ImportColumn::make('question_text')
                ->label('Pertanyaan')
                ->requiredMapping()
                ->rules(['required'])
                ->castStateUsing(fn (?string $state) => static::formatNumberedText($state))
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

            ImportColumn::make('topic_code')
                ->label('Kode Topik (opsional)')
                ->rules(['nullable', 'max:255'])
                ->fillRecordUsing(fn () => null)
                ->example('SISKUM-01'),

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
     * source_bank_id cuma fallback kalau importer ini dipicu di luar konteks
     * Bank Soal manapun. Kalau dipicu dari dalam halaman Bank Soal, options()
     * relation manager sudah otomatis kirim bank_id -- field ini tidak akan
     * tampil/dipakai karena context menang (lihat getBankId()).
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('source_bank_id')
                ->label('Bank Soal (sumber)')
                ->options(fn () => QuestionBank::pluck('title', 'id'))
                ->searchable()
                ->helperText('Wajib diisi kalau import ini dijalankan di luar halaman Bank Soal.'),
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
            'topic_id' => $this->resolveTopicId($bankId),
        ]);
    }

    /**
     * topic_code opsional -- soal boleh tidak ditag topik sama sekali
     * (topic_id tetap null, sesuai desain: mayoritas soal existing belum
     * ber-topic sampai proses tagging manual selesai). Kalau diisi,
     * di-resolve ke Topic yang di-scope ke taxonomy_id milik Bank Soal ini
     * (BUKAN taxonomy_id sembarang) -- supaya 1 kode topik yang sama di
     * taxonomy/kategori berbeda tidak collide/ketuker. Nama Topic baru
     * default-nya sama dengan kode-nya; admin bisa rename lewat TopicResource.
     */
    protected function resolveTopicId(?int $bankId): ?int
    {
        $topicCode = trim((string) ($this->data['topic_code'] ?? ''));

        if ($topicCode === '' || empty($bankId)) {
            return null;
        }

        $taxonomyId = QuestionBank::find($bankId)?->taxonomy_id;

        if (empty($taxonomyId)) {
            return null;
        }

        return Topic::firstOrCreate(
            [
                'taxonomy_id' => $taxonomyId,
                'code' => $topicCode,
            ],
            [
                'name' => $topicCode,
            ]
        )->id;
    }

    protected function beforeCreate(): void
    {
        if (empty($this->getBankId())) {
            throw new RowImportFailedException(
                'Bank Soal tidak diketahui. Jalankan import ini dari dalam halaman Bank Soal, atau pilih "Bank Soal (sumber)" di form sebelum import.'
            );
        }

        if ($this->data['type'] === 'pg') {
            $this->validateOptionsOrFail();
        }
    }

    /**
     * Deteksi pola penomoran manual dalam teks pertanyaan (mis. "Pilih yang
     * benar: 1. aku senang 2. kamu senang 3. saya senang") dan ubah jadi
     * HTML list (<ol><li>...) supaya FE bisa render sebagai list bernomor,
     * bukan paragraf datar. Kalau tidak ada minimal 2 angka berurutan yang
     * cocok pola ini, teks dibiarkan apa adanya (bukan list).
     */
    protected static function formatNumberedText(?string $text): ?string
    {
        if (blank($text)) {
            return $text;
        }

        $pattern = '/(?:^|\s)(\d{1,2})[\.\)]\s*/u';

        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return $text;
        }

        $positions = $matches[0];

        if (count($positions) < 2) {
            return $text;
        }

        $firstMatchStart = $positions[0][1];
        $intro = trim(substr($text, 0, $firstMatchStart));

        $items = [];
        for ($i = 0; $i < count($positions); $i++) {
            $start = $positions[$i][1] + strlen($positions[$i][0]);
            $end = $i + 1 < count($positions) ? $positions[$i + 1][1] : strlen($text);
            $itemText = trim(substr($text, $start, $end - $start));

            if ($itemText !== '') {
                $items[] = $itemText;
            }
        }

        if (count($items) < 2) {
            return $text;
        }

        $html = '';

        if ($intro !== '') {
            $html .= '<p>' . e($intro) . '</p>';
        }

        $html .= '<ol>';

        foreach ($items as $item) {
            $html .= '<li>' . e($item) . '</li>';
        }

        $html .= '</ol>';

        return $html;
    }

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
        if ($this->record->type !== 'pg') {
            return;
        }

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

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import soal selesai, ' . Number::format($import->successful_rows) . ' ' . str('baris')->plural($import->successful_rows) . ' berhasil diimport.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('baris')->plural($failedRowsCount) . ' gagal.';
        }

        return $body;
    }
}
