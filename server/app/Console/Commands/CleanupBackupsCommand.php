<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('messdb:cleanup {--days=4 : Number of days after month closure before deleting backups}')]
#[Description('Prune database backups of months closed for more than 4 days')]
class CleanupBackupsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $days = (int) $this->option('days');
        $this->info("Scanning for old backups of months closed for more than {$days} days...");

        $result = $backupService->cleanupOldBackups($days);

        if ($result['count'] === 0) {
            $this->info('No old backup files met the criteria for deletion.');
        } else {
            $this->info("Successfully pruned {$result['count']} old backup file(s):");
            foreach ($result['deleted_files'] as $file) {
                $this->line(" - Deleted: {$file}");
            }
        }

        return Command::SUCCESS;
    }
}
