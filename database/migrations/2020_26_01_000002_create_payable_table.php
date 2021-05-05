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
        Schema::table('payment_types', function (Blueprint $table) {
            $table->boolean('simple_checkout')->default(false)->after('slug');
        });

        Schema::table('payment_providers', function (Blueprint $table) {
            $table->dropColumn('simple_checkout');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->after('return_ip', function (Blueprint $table) {
                $table->datetime('canceled_at')->nullable()->default(null);
                $table->datetime('expires_at')->nullable()->default(null);
                $table->datetime('failed_at')->nullable()->default(null);
                $table->datetime('paid_at')->nullable()->default(null);
                $table->string('consumer_name')->nullable()->default(null);
                $table->string('consumer_account')->nullable()->default(null);
                $table->string('consumer_bic')->nullable()->default(null);
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_types', function (Blueprint $table) {
            $table->dropColumn('simple_checkout');
        });

        Schema::table('payment_providers', function (Blueprint $table) {
            $table->boolean('simple_checkout')->default(false)->after('type');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('canceled_at');
            $table->dropColumn('expires_at');
            $table->dropColumn('failed_at');
            $table->dropColumn('paid_at');
            $table->dropColumn('consumer_name');
            $table->dropColumn('consumer_account');
            $table->dropColumn('consumer_bic');
        });
    }
};
