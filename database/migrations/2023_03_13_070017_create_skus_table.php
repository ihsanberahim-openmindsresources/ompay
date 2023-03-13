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
        Schema::create('skus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code')->unique();
            $table->string('title')->fulltext('skus_title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->index();
            $table->string('currency')->nullable();
            $table->bigInteger('price')->nullable();
            $table->dateTime('exclusive_until')->nullable()->index();
            $table->boolean('is_bundle')->nullable()->index();
            $table->bigInteger('price_tier_id')->nullable()->index();
            $table->bigInteger('before_discount_tier_id')->nullable()->index();
            $table->bigInteger('image_id')->nullable()->index();
            $table->bigInteger('intro_media_id')->nullable()->index();
            $table->integer('seq_no')->nullable();
            $table->timestamps();
            $table->string('slug')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('skus');
    }
};
