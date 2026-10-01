<?php

namespace App\Console\Commands;

use App\Models\Month;
use App\Services\MonthService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('month:ensure-current {--year= : Specific year} {--month= : Specific month}')]
#[Description('Ensure the ongoing active month and its daily meal entries are pre-initialized')]
class EnsureCurrentMonthCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MonthService $monthService): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : now()->year;
        $monthNum = $this->option('month') ? (int) $this->option('month') : now()->month;

        $this->info("Checking active month for {$year}-{$monthNum}...");

        $month = Month::where('year', $year)->where('month', $monthNum)->first();

        if ($month) {
            $this->info("Month #{$month->id} ({$year}-{$monthNum}) is already initialized.");
        } else {
            $month = Month::firstOrCreate([
                'year' => $year,
                'month' => $monthNum,
            ]);

            $this->info("Successfully initialized Month #{$month->id} ({$year}-{$monthNum}) and pre-seeded all daily meal entries for active roommates.");
        }

        return Command::SUCCESS;
    }
}
