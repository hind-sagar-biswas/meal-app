<?php

use App\Models\Month;
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
        Schema::create('month_results', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Month::class)->unique()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('participant_count');
            
            $table->unsignedSmallInteger('breakfast_count');
            $table->unsignedSmallInteger('meal_count'); // = Lunch + Dinner

            $table->unsignedSmallInteger('total_expense'); // = Sum of month's expense
            $table->unsignedSmallInteger('bazar_expense'); // = Sum of month's expense (is_grouped = false)
            $table->unsignedSmallInteger('grouped_expense'); // = Sum of month's expense (is_grouped = true)

            $table->unsignedSmallInteger('breakfast_expense'); // = Breakfast Count * Month's breakfast price
            $table->unsignedSmallInteger('meal_expense'); // = bazar_expense - breakfast_expense
            
            $table->unsignedInteger('meal_rate'); // = (meal_expense / meal_count) * 1,000,000 [stored with 4 digit precision]
            $table->unsignedInteger('group_expense_per_person'); // = (grouped_expense / participant_count) * 1,000,000 [stored with 4 digit precision]
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('month_results');
    }
};
