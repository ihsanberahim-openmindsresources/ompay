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
        Schema::create('android_pay_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('notification_type');
            $table->string('auto_renew_product_id')->nullable();
            $table->longText('payload');
            $table->bigInteger('user_id')->nullable()->index();
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
        Schema::dropIfExists('android_pay_notifications');
    }
};
