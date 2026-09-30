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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Month::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('date');
            $table->string('cause');
            $table->string('note')->nullable();
            $table->boolean('is_grouped')->default(true);
            $table->integer('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
