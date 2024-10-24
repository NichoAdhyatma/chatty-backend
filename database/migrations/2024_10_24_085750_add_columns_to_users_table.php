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
        Schema::table('users', function (Blueprint $table) {
            $table->string('access_token')->nullable();
            $table->string('avatar')->nullable();
            $table->string('description')->nullable();
            $table->datetime('expire_date')->nullable();
            $table->string('fcm_token')->nullable();
            $table->integer('online')->nullable();
            $table->integer('open_id')->nullable();
            $table->string('token')->nullable();
            $table->integer('type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
