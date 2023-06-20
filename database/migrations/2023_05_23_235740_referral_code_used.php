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
        //add refferral_code_used column to users table and make default null
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code_used')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //drop refferral_code_used column from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referral_code_used');
        });
    }
};
