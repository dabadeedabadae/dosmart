<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Отметка «в возврат вошла доставка» — чтобы лимит считал возврат только товаров. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('refund_includes_delivery')->default(false)->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('refund_includes_delivery'));
    }
};
