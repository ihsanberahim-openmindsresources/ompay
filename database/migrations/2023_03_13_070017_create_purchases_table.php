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
        Schema::create('purchases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->index();
            $table->integer('status');
            $table->string('platform');
            $table->bigInteger('price_tier_id')->nullable()->index();
            $table->bigInteger('sku_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('transaction_id')->nullable()->index();
            $table->string('receipt_type')->nullable();
            $table->dateTime('purchase_date')->nullable();
            $table->longText('receipt_payload')->nullable();
            $table->timestamps();

            $table->index(['platform', 'transaction_id']);
            $table->index(['user_id', 'status', 'price_tier_id']);
            $table->index(['user_id', 'status', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('purchases');
    }
};
