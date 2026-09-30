<?php

namespace App\Console\Commands;

use App\Models\Meal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meal:log')]
#[Description("Log today's meals for all active users")]
class LogMealsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = Meal::logToday();

        if ($count > 0) {
            $this->info("Successfully logged {$count} meal(s) for today (".now()->toDateString().').');
        } else {
            $this->info('No unlogged meals found for active users today ('.now()->toDateString().').');
        }

        return Command::SUCCESS;
    }
}
