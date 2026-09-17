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
        if (Schema::hasColumn('payments', 'payable_snapshot')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->json('payable_snapshot')->nullable()->default(null)->after('remaining_amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasColumn('payments', 'payable_snapshot')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payable_snapshot');
        });
    }
};
