<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('buckaroo_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->morphs('subscribable');
            $table->string('subscription_guid')->nullable()->default(null);
            $table->string('rate_plan_guid')->nullable()->default(null);
            $table->string('configuration_code')->nullable()->default(null);
            $table->string('rate_plan_charge_cuid')->nullable()->default(null);
            $table->string('base_number_of_units')->nullable()->default(null);
            $table->string('price_per_unit')->nullable()->default(null);
            $table->date('start_date')->nullable()->default(null);
            $table->date('end_date')->nullable()->default(null);
            $table->date('resume_date')->nullable()->default(null);
            $table->string('rate_plan_code')->nullable()->default(null);
            $table->string('debtor_code')->nullable()->default(null);
            $table->json('subscriptions')->nullable()->default(null);
            $table->json('services')->nullable()->default(null);
            $table->json('custom_parameters')->nullable()->default(null);
            $table->json('additional_parameters')->nullable()->default(null);
            $table->json('request_errors')->nullable()->default(null);
            $table->boolean('is_test')->nullable()->default(null);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('buckaroo_subscriptions');
    }
};
