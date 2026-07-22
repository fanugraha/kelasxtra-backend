<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * WIPE DATA TESTING -- sekali jalan untuk reset total sebelum
     * migrasi skema taxonomy_id di question_banks & packages.
     * DESTRUKTIF & TIDAK BISA DI-ROLLBACK lewat down(). Backup dulu manual.
     */
    protected array $keep = [
        'users',
        'migrations',
        'password_reset_tokens',
        'sessions',
        'personal_access_tokens',
        'failed_jobs',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
    ];

    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $dbName = DB::getDatabaseName();
        $tables = DB::select('SHOW TABLES');
        $key = "Tables_in_{$dbName}";

        foreach ($tables as $row) {
            $table = $row->$key;
            if (in_array($table, $this->keep, true)) {
                continue;
            }
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // Tidak ada rollback otomatis untuk truncate.
        // Kalau perlu, restore dari mysqldump backup manual.
    }
};
