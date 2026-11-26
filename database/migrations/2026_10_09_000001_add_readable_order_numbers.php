<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('institutions', fn (Blueprint $table) => $table->string('order_code', 2)->nullable()->unique());
        Schema::table('orders', fn (Blueprint $table) => $table->string('display_number', 20)->nullable()->unique());
        // Internal directory codes, editable by staff before their first use.
        foreach (DB::table('institutions')->whereBetween('id', [1, 99])->get(['id']) as $institution) {
            DB::table('institutions')->where('id', $institution->id)->update(['order_code' => str_pad($institution->id, 2, '0', STR_PAD_LEFT)]);
        }
    }
    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('display_number'));
        Schema::table('institutions', fn (Blueprint $table) => $table->dropColumn('order_code'));
    }
};
