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
        Schema::create('activation_code_user', function (Blueprint $table) {
            $table->bigInteger('activation_code_id')->index();
            $table->bigInteger('user_id')->index();
            $table->integer('discount_remaining')->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'activation_code_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activation_code_user');
    }
};
