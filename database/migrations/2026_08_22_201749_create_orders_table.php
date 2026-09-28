<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // DOS-00001
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            // Данные получателя
            $table->string('prisoner_name');
            $table->string('institution_name');   // текст на случай если нет в справочнике
            $table->string('contact_phone');
            // Суммы
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(3000);
            $table->decimal('total', 10, 2);
            // Статус
            $table->enum('status', [
                'pending',      // ожидает оплаты
                'paid',         // оплачен
                'checking',     // проверка товаров оператором
                'processing',   // собирается
                'delivering',   // в пути
                'delivered',    // доставлен
                'cancelled',    // отменён
            ])->default('pending');
            // Оплата
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->string('kaspi_transaction_id')->nullable();
            $table->string('notes')->nullable();  // заметки оператора
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
