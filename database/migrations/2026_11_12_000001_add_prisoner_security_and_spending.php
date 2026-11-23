<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prisoners', function (Blueprint $table) {
            // unlimited — работает / тратит заработанное; norm — норма учреждения в МРП; custom — сумма вручную.
            $table->string('spending_mode', 16)->default('unlimited');
            $table->string('security_question', 32)->nullable();
            $table->string('security_answer_hash')->nullable();
            $table->unsignedSmallInteger('failed_logins')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
        });
        DB::table('prisoners')->whereNotNull('monthly_limit_minor')->update(['spending_mode' => 'custom']);
        Schema::table('institutions', function (Blueprint $table) {
            $table->json('spending_limits_mrp')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('institutions', fn (Blueprint $table) => $table->dropColumn('spending_limits_mrp'));
        Schema::table('prisoners', fn (Blueprint $table) => $table->dropColumn([
            'spending_mode', 'security_question', 'security_answer_hash', 'failed_logins', 'locked_until', 'last_login_at',
        ]));
    }
};
