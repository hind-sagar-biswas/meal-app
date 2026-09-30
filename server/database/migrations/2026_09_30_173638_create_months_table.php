<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('months', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('breakfast_price')->default(20);
            $table->boolean('is_closed')->default(false);
            $table->foreignIdFor(User::class, 'closed_by')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        DB::statement('ALTER TABLE months ADD CONSTRAINT chk_valid_year CHECK (year >= 2026 AND year < 2029)');
        DB::statement('ALTER TABLE months ADD CONSTRAINT chk_valid_month CHECK (month >= 1 AND month <= 12)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE months DROP CONSTRAINT chk_valid_month');
        DB::statement('ALTER TABLE months DROP CONSTRAINT chk_valid_year');
        Schema::dropIfExists('months');
    }
};
