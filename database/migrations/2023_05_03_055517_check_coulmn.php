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
        //add check coulmn default 0 to subscription time table
        Schema::table('subscribtion_time', function (Blueprint $table) {
            $table->boolean('check')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //drop check coulmn from subscription time table
        Schema::table('subscribtion_time', function (Blueprint $table) {
            $table->dropColumn('check');
        });
    }
};
