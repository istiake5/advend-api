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
        //add credits to referral_systems table
        Schema::table('referral_systems', function (Blueprint $table) {
            $table->integer('credits')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //drop credits from referral_systems table
        Schema::table('referral_systems', function (Blueprint $table) {
            $table->dropColumn('credits');
        });
    }
};
