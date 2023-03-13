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
        Schema::create('sku_user', function (Blueprint $table) {
            $table->bigInteger('user_id')->index();
            $table->bigInteger('sku_id')->index();
            $table->timestamps();

            $table->primary(['user_id', 'sku_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sku_user');
    }
};
