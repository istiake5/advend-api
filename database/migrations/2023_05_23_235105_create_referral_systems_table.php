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
        Schema::create('referral_systems', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            //foreign key user_id
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('number_of_uses')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_systems');
    }
};
