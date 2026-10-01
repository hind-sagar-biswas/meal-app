<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('messdb:backup {--filename= : Optional custom filename for the backup}')]
#[Description('Create an atomic gzipped SQL snapshot of the mess database')]
class BackupDatabaseCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $this->info('Starting database backup...');

        $customFilename = $this->option('filename');
        $result = $backupService->createBackup($customFilename);

        $sizeFormatted = number_format($result['size_bytes'] / 1024, 2).' KB';

        $this->info("Backup created successfully: {$result['filename']} ({$sizeFormatted})");
        $this->line("Location: {$result['path']}");

        return Command::SUCCESS;
    }
}
