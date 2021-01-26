<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayableTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('type');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_provider_id');
            $table->string('name');
            $table->string('slug');
            $table->string('commission_type')->default(null)->nullable();
            $table->bigInteger('commission_amount')->default(null)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });


        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->bigInteger('total_amount')->default(0);
            $table->bigInteger('paid_amount')->default(0);
            $table->bigInteger('remaining_amount')->default(0);
            $table->unsignedBigInteger('payment_provider_id');
            $table->unsignedBigInteger('payment_type_id');
            $table->timestamp('started')->nullable()->default(null);
            $table->timestamp('status_changed_at')->nullable()->default(null);
            $table->integer('status_change_count')->default(0);
            $table->string('provider_id')->nullable()->default(null);
            $table->string('status_code')->nullable()->default(null);
            $table->string('status')->nullable()->default(null);
            $table->boolean('completed')->default(false);
            $table->boolean('is_test')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payment_provider_id')->references('id')->on('payment_providers');
            $table->foreign('payment_type_id')->references('id')->on('payment_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_providers');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payments');
    }
}
