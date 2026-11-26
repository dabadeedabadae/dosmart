<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->json('parcel_limits_mrp')->nullable();
            $table->json('parcel_mrp_rates')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('institutions', fn (Blueprint $table) => $table->dropColumn(['parcel_limits_mrp', 'parcel_mrp_rates']));
    }
};
