<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('activation_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code')->unique();
            $table->integer('available_seats');
            $table->integer('total_seats');
            $table->bigInteger('order_id')->nullable()->index();
            $table->bigInteger('sku_id')->index();
            $table->boolean('is_active');
            $table->bigInteger('activation_code_type_id')->nullable()->index();
            $table->integer('discount_percent')->nullable();
            $table->bigInteger('discount_value')->nullable();
            $table->integer('discount_limit')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activation_codes');
    }
};
