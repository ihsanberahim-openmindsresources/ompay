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
        Schema::create('ios_pay_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('environment');
            $table->string('notification_type');
            $table->string('auto_renew_product_id')->nullable();
            $table->boolean('auto_renew_status')->nullable();
            $table->dateTime('auto_renew_status_change_date')->nullable();
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
        Schema::dropIfExists('ios_pay_notifications');
    }
};
