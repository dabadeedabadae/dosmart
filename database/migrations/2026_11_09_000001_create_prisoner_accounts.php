<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->json('parcel_limits')->nullable();
        });
        Schema::create('prisoners', function (Blueprint $table) {
            $table->id();
            $table->string('iin', 12)->unique();
            $table->string('full_name');
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('condition', 16);
            $table->unsignedBigInteger('balance_minor')->default(0);
            $table->unsignedBigInteger('monthly_limit_minor')->nullable();
            $table->string('access_hash')->nullable();
            $table->string('parcel_code', 24)->unique();
            $table->timestamps();
        });
        Schema::create('prisoner_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prisoner_id')->constrained()->restrictOnDelete();
            $table->string('application_number', 100);
            $table->date('application_date');
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 16)->default('pending');
            $table->string('payment_reference', 100)->nullable()->unique();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason')->nullable();
            $table->timestamps();
            $table->unique(['prisoner_id', 'application_number', 'application_date']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('prisoner_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('prisoner_order_type', 16)->nullable();
            $table->string('parcel_state', 16)->nullable();
            $table->unsignedSmallInteger('parcel_year')->nullable();
            $table->uuid('purchase_request_id')->nullable();
            $table->string('purchase_hash', 64)->nullable();
            $table->unique(['prisoner_id', 'purchase_request_id']);
            $table->index(['prisoner_id', 'parcel_state', 'parcel_year']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['prisoner_id', 'parcel_state', 'parcel_year']);
            $table->dropUnique(['prisoner_id', 'purchase_request_id']);
            $table->dropConstrainedForeignId('prisoner_id');
            $table->dropColumn(['prisoner_order_type', 'parcel_state', 'parcel_year', 'purchase_request_id', 'purchase_hash']);
        });
        Schema::dropIfExists('prisoner_transfers');
        Schema::dropIfExists('prisoners');
        Schema::table('institutions', fn (Blueprint $table) => $table->dropColumn('parcel_limits'));
    }
};
