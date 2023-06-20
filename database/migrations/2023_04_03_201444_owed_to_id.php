<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('owed_amounts', function (Blueprint $table) {
            $table->integer('owed_to_advend_amount');
            $table->unsignedBigInteger('owed_to_id');
            $table->foreign('owed_to_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owed_amounts', function (Blueprint $table) {
            $table->dropForeign('owed_to_id');
            $table->dropColumn('owed_to_id');
            $table->dropColumn('owed_to_advend_amount');
        });
    }
};
