<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_logs', function (Blueprint $table) {
            // webhook | reconcile | admin_manual — nullable supaya log lama
            // (sebelum kolom ini ada) tidak error.
            $table->string('source')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_logs', function (Blueprint $table) {
            $table->dropForeign(['changed_by']);
            $table->dropColumn(['source', 'changed_by']);
        });
    }
};
