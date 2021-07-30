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
        Schema::table('payments', function (Blueprint $table) {
            $table->after('result_payload', function (Blueprint $table) {
                $table->text('creation_payload_text')->nullable()->default(null);
                $table->text('result_payload_text')->nullable()->default(null);
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
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('creation_payload_text');
            $table->dropColumn('result_payload_text');
        });
    }
};
