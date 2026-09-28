<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_drafts', function (Blueprint $table) {
            $table->string('submission_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_drafts', fn (Blueprint $table) => $table->dropColumn('submission_hash'));
    }
};
