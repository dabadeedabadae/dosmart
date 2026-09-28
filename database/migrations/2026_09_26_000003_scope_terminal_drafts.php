<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_drafts', fn (Blueprint $table) => $table->string('terminal_subject', 64)->nullable()->index());
    }

    public function down(): void
    {
        Schema::table('order_drafts', fn (Blueprint $table) => $table->dropColumn('terminal_subject'));
    }
};
