<?php

use App\Models\Month;
use App\Models\User;
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
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Month::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->date('date');

            $table->unsignedTinyInteger('breakfast')->default(0);
            $table->unsignedTinyInteger('lunch')->default(1);
            $table->unsignedTinyInteger('dinner')->default(1);

            $table->boolean('has_logged')->default(false); // A whole month's meal is generated, but only logged automatically after the day ends and UI only shows the logged ones

            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index(['month_id', 'has_logged', 'user_id']);
            $table->index(['date', 'has_logged']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
