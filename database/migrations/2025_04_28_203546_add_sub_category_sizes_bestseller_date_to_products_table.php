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
        Schema::table('products', function (Blueprint $table) {
            $table->string('sub_category')->nullable();
            $table->json('sizes')->nullable();
            $table->boolean('bestseller')->default(false);
            $table->timestamp('date')->nullable();
        });
    }
    
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sub_category', 'sizes', 'bestseller', 'date']);
        });
    }
    
};
