<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Регистрация по ФИО + условиям + дате рождения, пароль 6 цифр.
     * Забытый пароль восстанавливается по дате рождения (по умолчанию) или контрольному вопросу:
     * не больше 3 неудачных попыток, потом блокировка на сутки.
     */
    public function up(): void
    {
        Schema::table('prisoners', function (Blueprint $table) {
            $table->date('birth_date')->nullable();
            $table->string('recovery_method', 16)->default('birth_date');
            $table->unsignedTinyInteger('recovery_failed')->default(0);
            $table->timestamp('recovery_locked_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('prisoners', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'recovery_method', 'recovery_failed', 'recovery_locked_until']);
        });
    }
};
