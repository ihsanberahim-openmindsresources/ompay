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
        Schema::create('activation_code_sku', function (Blueprint $table) {
            $table->bigInteger('activation_code_id')->index();
            $table->bigInteger('sku_id')->index();
            $table->timestamps();

            $table->primary(['activation_code_id', 'sku_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activation_code_sku');
    }
};
