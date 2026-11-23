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
            // Список КУИС может прийти без ИИН: человек находит себя по ФИО и отряду.
            $table->string('iin', 12)->nullable()->change();
            $table->string('squad', 50)->nullable();
            $table->string('pin_hash')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->string('family_code_hash', 64)->nullable()->unique();
            $table->timestamp('family_code_set_at')->nullable();
            // С какого месяца действует лимит — от него считается перенос остатка (ч. 2 ст. 105 УИК).
            $table->date('limit_started_on')->nullable();
            $table->index(['institution_id', 'squad']);
        });
        DB::table('prisoners')->where('spending_mode', '!=', 'unlimited')
            ->update(['limit_started_on' => now('Asia/Almaty')->startOfMonth()->toDateString()]);

        Schema::table('institutions', function (Blueprint $table) {
            $table->string('tablet_code', 16)->nullable()->unique();
        });

        Schema::create('prisoner_limit_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prisoner_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->unsignedBigInteger('limit_minor');
            $table->timestamps();
            $table->unique(['prisoner_id', 'period']);
        });

        Schema::table('prisoner_transfers', function (Blueprint $table) {
            $table->string('source', 16)->default('application');
            $table->string('sender_name')->nullable();
        });

        Schema::create('deposit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prisoner_id')->constrained()->restrictOnDelete();
            $table->string('sender_name');
            $table->string('sender_phone', 20);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 16)->default('new');
            $table->foreignId('transfer_id')->nullable()->constrained('prisoner_transfers')->restrictOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_requests');
        Schema::table('prisoner_transfers', fn (Blueprint $table) => $table->dropColumn(['source', 'sender_name']));
        Schema::dropIfExists('prisoner_limit_periods');
        Schema::table('institutions', fn (Blueprint $table) => $table->dropColumn('tablet_code'));
        Schema::table('prisoners', function (Blueprint $table) {
            $table->dropIndex(['institution_id', 'squad']);
            $table->dropColumn(['squad', 'pin_hash', 'registered_at', 'family_code_hash', 'family_code_set_at', 'limit_started_on']);
        });
    }
};
