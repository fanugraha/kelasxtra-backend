<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Models\QuestionBank;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListQuestions extends ListRecords
{
    protected static string $resource = QuestionResource::class;

    // Halaman ini READ-ONLY (lihat komentar di QuestionResource::class) --
    // canCreate() sudah false, jadi header action Create sengaja DIHAPUS
    // di sini juga. Sebelumnya ada CreateAction::make() yang nyantol dari
    // scaffolding awal, padahal nggak pernah valid dipakai (bug: tombol
    // "New" muncul tapi resource ini sudah dikunci read-only).

    /**
     * Tab per Bank Soal -- bukan per Program, karena halaman ini cuma buat
     * audit data lama, bukan tempat kerja harian admin. Filter Program yang
     * sudah ada (dropdown) tetap dipertahankan buat pencarian lintas bank;
     * tab di sini melengkapi dengan cara cepat "lihat semua soal legacy
     * dalam 1 bank tertentu" tanpa perlu isi search box manual.
     */
    public function getTabs(): array
    {
        return [];
    }
}
