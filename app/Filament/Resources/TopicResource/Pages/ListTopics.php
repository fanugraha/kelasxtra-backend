<?php

namespace App\Filament\Resources\TopicResource\Pages;

use App\Filament\Resources\TopicResource;
use App\Models\Program;
use App\Models\Topic;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\HtmlString;

class ListTopics extends ListRecords
{
    protected static string $resource = TopicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            // Dipanggil terprogram lewat $livewire->mountAction() dari dalam
            // bulk action "Generate Part Massal" (lihat TopicResource.php) --
            // bukan tombol yang tampil, cuma cara nampilin hasil generate
            // dalam modal terpisah (tabel), bukan notifikasi 1 paragraf panjang.
            Action::make('generatePartMassalResult')
                ->label('Hasil Generate Part Massal')
                ->modalHeading('Hasil Generate Part Massal')
                ->modalContent(fn (array $arguments) => new HtmlString(
                    $this->renderGeneratePartMassalResult(
                        $arguments['success'] ?? [],
                        $arguments['skipped'] ?? []
                    )
                ))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->hidden(),
        ];
    }

    protected function renderGeneratePartMassalResult(array $success, array $skipped): string
    {
        $rows = '';

        foreach ($success as $item) {
            $rows .= '<tr class="border-b border-gray-100 dark:border-white/5">'
                . '<td class="py-2 pr-4 font-medium">' . e($item['topic']) . '</td>'
                . '<td class="py-2 pr-4 text-gray-500">' . e($item['category']) . '</td>'
                . '<td class="py-2 pr-4"><span class="inline-flex items-center rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-400/10 dark:text-success-400">Berhasil</span></td>'
                . '<td class="py-2 text-gray-500">Part ' . e($item['part']) . '</td>'
                . '</tr>';
        }

        foreach ($skipped as $item) {
            $rows .= '<tr class="border-b border-gray-100 dark:border-white/5">'
                . '<td class="py-2 pr-4 font-medium">' . e($item['topic']) . '</td>'
                . '<td class="py-2 pr-4 text-gray-500">' . e($item['category']) . '</td>'
                . '<td class="py-2 pr-4"><span class="inline-flex items-center rounded-full bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">Dilewati</span></td>'
                . '<td class="py-2 text-gray-500">' . e($item['reason']) . '</td>'
                . '</tr>';
        }

        $summary = count($success) . ' berhasil, ' . count($skipped) . ' dilewati.';

        return '<div class="space-y-3">'
            . '<p class="text-sm text-gray-600 dark:text-gray-400">' . e($summary) . '</p>'
            . '<div class="overflow-x-auto">'
            . '<table class="w-full text-left text-sm">'
            . '<thead><tr class="border-b border-gray-200 dark:border-white/10 text-xs uppercase text-gray-500">'
            . '<th class="py-2 pr-4">Topik</th><th class="py-2 pr-4">Kategori</th><th class="py-2 pr-4">Status</th><th class="py-2">Keterangan</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '</div>'
            . '</div>';
    }

    /**
     * Tab per Program + badge jumlah topik, pola sama seperti Bank Soals
     * & Exams. Topic tidak punya program_id langsung (cuma taxonomy_id),
     * jadi hitung agregatnya lewat join ke taxonomies -- tetap 1 query,
     * bukan query per-tab di dalam loop.
     */
    public function getTabs(): array
    {
        return [];
    }
}
