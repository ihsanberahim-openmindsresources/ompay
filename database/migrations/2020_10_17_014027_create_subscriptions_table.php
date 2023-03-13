<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable()->index();
            $table->string('platform');
            $table->string('transaction_id')->index();
            $table->string('original_transaction_id')->nullable()->index();
            $table->string('receipt_type');
            $table->bigInteger('product_id')->nullable()->index();
            $table->dateTime('purchase_date')->nullable();
            $table->dateTime('expires_date')->nullable()->index();
            $table->dateTime('cancellation_date')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->longText('receipt_payload')->nullable();
            $table->timestamps();

            $table->index(['platform', 'transaction_id']);
            $table->index(['user_id', 'expires_date', 'cancellation_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscriptions');
    }
}
