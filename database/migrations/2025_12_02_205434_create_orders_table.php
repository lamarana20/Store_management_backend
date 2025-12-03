<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::create('orders', function (Blueprint $table) {
        $table->id();

        // ---- User ----
        $table->foreignId('user_id')->constrained()->onDelete('cascade');

        // ---- Items ----
        $table->json('items');

        // ---- Pricing ----
        $table->decimal('subtotal', 10, 2);
        $table->decimal('delivery_fee', 10, 2);
        $table->decimal('total', 10, 2);

        // ---- Payment ----
        $table->string('payment_method');
        $table->string('payment_status')->default('pending');
        $table->string('order_status')->default('pending');

        // ---- Delivery Info ----
        $table->string('delivery_first_name');
        $table->string('delivery_last_name')->nullable();
        $table->string('delivery_email');
        $table->string('delivery_phone')->nullable();
        $table->string('delivery_address');
        $table->string('delivery_city');
        $table->string('delivery_state')->nullable();
        $table->string('delivery_zip')->nullable();
        $table->string('delivery_country')->nullable();

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
