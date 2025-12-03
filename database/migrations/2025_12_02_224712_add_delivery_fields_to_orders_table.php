<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::table('orders', function (Blueprint $table) {
        if (!Schema::hasColumn('orders', 'delivery_first_name')) {
            $table->string('delivery_first_name')->nullable();
        }
        if (!Schema::hasColumn('orders', 'delivery_last_name')) {
            $table->string('delivery_last_name')->nullable();
        }
        if (!Schema::hasColumn('orders', 'delivery_email')) {
            $table->string('delivery_email')->nullable();
        }
        if (!Schema::hasColumn('orders', 'delivery_phone')) {
            $table->string('delivery_phone')->nullable();
        }
        if (!Schema::hasColumn('orders', 'delivery_address')) {
            $table->text('delivery_address')->nullable();
        }
        if (!Schema::hasColumn('orders', 'delivery_city')) {
            $table->string('delivery_city')->nullable();
        }
        if (!Schema::hasColumn('orders', 'delivery_state')) {
            $table->string('delivery_state')->nullable();
        }
        if (!Schema::hasColumn('orders', 'delivery_zip')) {
            $table->string('delivery_zip')->nullable();
        }
        if (!Schema::hasColumn('orders', 'delivery_country')) {
            $table->string('delivery_country')->nullable();
        }
    });
}


    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_city', 'delivery_state', 'delivery_zip', 'delivery_country']);
        });
    }
};