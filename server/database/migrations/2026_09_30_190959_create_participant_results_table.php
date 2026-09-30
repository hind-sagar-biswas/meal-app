<?php

use App\Models\Month;
use App\Models\MonthResult;
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
        Schema::create('participant_results', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Month::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(MonthResult::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('breakfast_count');
            $table->unsignedSmallInteger('meal_count');

            $table->unsignedSmallInteger('breakfast_expense');   // = breakfast_count * month's breakfast price
            $table->unsignedSmallInteger('meal_expense');        // = meal_count * month's meal rate
            $table->unsignedSmallInteger('group_expense');       // = group_expense_per_person

            $table->unsignedSmallInteger('total_expense');       // = breakfast_expense + meal_expense + group_expense
            $table->unsignedSmallInteger('total_contribution');  // = sum of this month's contributions

            $table->smallInteger('adjustment');                  // = total_expense - total_contribution [+ve -> still need to pay, -ve -> will get money back]

            $table->timestamps();

            $table->unique(['month_result_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('participant_results');
    }
};
