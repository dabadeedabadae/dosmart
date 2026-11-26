<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Самостоятельная регистрация на планшете: новая карточка ждёт проверки сотрудником
     * (условия содержания, режим лимита). Карточки, заведённые сотрудником, считаются проверенными.
     * Восстановление пароля — не больше 3 попыток за сутки, время последнего восстановления видно в админке.
     */
    public function up(): void
    {
        Schema::table('prisoners', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('recovery_window_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
        });
        DB::table('prisoners')->update(['reviewed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('prisoners', function (Blueprint $table) {
            $table->dropColumn(['reviewed_at', 'recovery_window_at', 'recovered_at']);
        });
    }
};
