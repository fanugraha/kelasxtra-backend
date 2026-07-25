<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Badge singkat di card (mis. "Paling Hemat", "Semua Program").
            // Terpisah dari name -- name tetap identitas plan, tagline murni
            // untuk daya tarik visual dan bisa diubah tanpa mengubah nama.
            $table->string('tagline')->nullable()->after('name');

            // Paragraf penjelasan singkat di bawah nama plan.
            $table->text('description')->nullable()->after('tagline');

            // Daftar benefit as bullet points, array string. JSON supaya
            // panjangnya fleksibel per plan tanpa perlu tabel terpisah.
            $table->json('features')->nullable()->after('description');

            // Highlight 1 plan sebagai "Paling Populer" ala Netflix/Spotify --
            // hanya berpengaruh ke tampilan, tidak ke logic checkout.
            $table->boolean('is_featured')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'description', 'features', 'is_featured']);
        });
    }
};
