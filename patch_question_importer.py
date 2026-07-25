path = "app/Filament/Imports/QuestionImporter.php"

with open(path, "r") as f:
    content = f.read()

old_column = """            ImportColumn::make('question_text')
                ->label('Pertanyaan')
                ->requiredMapping()
                ->rules(['required'])
                ->example('According to the passage, what do green plants produce?'),"""

new_column = """            ImportColumn::make('question_text')
                ->label('Pertanyaan')
                ->requiredMapping()
                ->rules(['required'])
                ->castStateUsing(fn (?string $state) => static::formatNumberedText($state))
                ->example('According to the passage, what do green plants produce?'),"""

if old_column not in content:
    raise SystemExit("question_text column pattern not found — file may have changed.")

content = content.replace(old_column, new_column)

old_marker = "    protected function parseCorrectFlag(string $rawValue): ?bool"

new_method = """    /**
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

        $pattern = '/(?:^|\\s)(\\d{1,2})[\\.\\)]\\s*/u';

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

    protected function parseCorrectFlag(string $rawValue): ?bool"""

if old_marker not in content:
    raise SystemExit("parseCorrectFlag marker not found — file may have changed.")

content = content.replace(old_marker, new_method)

with open(path, "w") as f:
    f.write(content)

print("Patched QuestionImporter.php successfully.")
