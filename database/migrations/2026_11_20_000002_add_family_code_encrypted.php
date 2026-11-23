<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Код для родственников теперь можно посмотреть повторно — храним его зашифрованным.
        Schema::table('prisoners', fn (Blueprint $table) => $table->text('family_code_encrypted')->nullable());
    }

    public function down(): void
    {
        Schema::table('prisoners', fn (Blueprint $table) => $table->dropColumn('family_code_encrypted'));
    }
};
