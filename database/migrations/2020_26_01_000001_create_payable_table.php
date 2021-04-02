<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
            $table->boolean('simple_checkout')->default(false);
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_provider_id');
            $table->string('name');
            $table->string('slug');
            $table->string('icon')->nullable()->default(null);
            $table->text('description')->nullable()->default(null);
            $table->text('notice')->nullable()->default(null);
            $table->string('vendor_type_id')->nullable()->default(null);
            $table->json('vendor_type_options')->nullable()->default(null);
            $table->string('commission_type')->default(null)->nullable();
            $table->bigInteger('commission_amount')->default(null)->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });


        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('payable_type', 255);
            $table->uuid('payable_id');
            $table->unsignedBigInteger('payment_provider_id');
            $table->unsignedBigInteger('payment_type_id');
            $table->boolean('simple_checkout');
            $table->bigInteger('total_amount')->default(0);
            $table->bigInteger('paid_amount')->default(0);
            $table->bigInteger('remaining_amount')->default(0);
            $table->timestamp('started');
            $table->timestamp('status_changed_at')->nullable()->default(null);
            $table->integer('status_change_count')->default(0);
            $table->string('provider_id')->nullable()->default(null);
            $table->string('status_code')->nullable()->default(null);
            $table->string('status')->nullable()->default(null);
            $table->boolean('completed')->default(false);
            $table->boolean('is_test')->default(false);
            $table->string('start_ip');
            $table->string('return_ip')->nullable()->default(null);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payment_provider_id', 'payment_provider_id')->references('id')->on('payment_providers');
            $table->foreign('payment_type_id', 'payment_type_id')->references('id')->on('payment_types');
        });

        Schema::create('payment_statuses', function (Blueprint $table) {
            $table->id();
            $table->uuid('payment_id');
            $table->string('status_code')->nullable()->default(null);
            $table->string('status')->nullable()->default(null);
            $table->string('return_ip')->nullable()->default(null);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payment_id', 'payment_statusses_payment_id')->references('id')->on('payments');
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->uuid('payment_id');
            $table->string('uri')->nullable()->default(null);
            $table->json('get_payload')->nullable()->default(null);
            $table->json('post_payload')->nullable()->default(null);
            $table->string('status')->nullable()->default(null);
            $table->string('return_ip')->nullable()->default(null);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('payment_id', 'payment_webhooks_payment_id')->references('id')->on('payments');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_types');
        Schema::dropIfExists('payment_providers');
    }
}
