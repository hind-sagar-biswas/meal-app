<?php

use App\Models\Month;
use App\Models\User;
use App\Services\BackupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->backupDir = storage_path('app/'.BackupService::BACKUP_DIRECTORY);
    if (File::exists($this->backupDir)) {
        File::cleanDirectory($this->backupDir);
    }
});

afterEach(function () {
    if (File::exists($this->backupDir)) {
        File::cleanDirectory($this->backupDir);
    }
});

test('messdb:backup command creates a valid gzipped SQL backup file', function () {
    User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
    Month::factory()->create(['year' => 2026, 'month' => 10]);

    $this->artisan('messdb:backup')
        ->assertSuccessful()
        ->expectsOutputToContain('Backup created successfully');

    $files = File::files($this->backupDir);
    expect(count($files))->toBe(1);

    $backupFile = $files[0];
    expect($backupFile->getExtension())->toBe('gz')
        ->and(filesize($backupFile->getPathname()))->toBeGreaterThan(0);

    // Verify gzipped SQL content
    $uncompressed = gzdecode(File::get($backupFile->getPathname()));
    expect($uncompressed)->toContain('-- MessApp Database Backup')
        ->and($uncompressed)->toContain('users')
        ->and($uncompressed)->toContain('alice@example.com');
});

test('messdb:backup supports custom filename option', function () {
    $customName = 'manual_test_backup.sql.gz';

    $this->artisan('messdb:backup', ['--filename' => $customName])
        ->assertSuccessful()
        ->expectsOutputToContain($customName);

    $targetPath = storage_path('app/backups/'.$customName);
    expect(File::exists($targetPath))->toBeTrue();
});

test('messdb:cleanup deletes backups belonging to months closed more than 4 days ago', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00'));

    // Month 5 closed 5 months ago
    $closedMonth5 = Month::factory()->create([
        'year' => 2026,
        'month' => 5,
        'is_closed' => true,
        'closed_at' => Carbon::parse('2026-06-01 10:00:00'),
    ]);

    // Ongoing active month 10
    $ongoingMonth10 = Month::factory()->create([
        'year' => 2026,
        'month' => 10,
        'is_closed' => false,
        'closed_at' => null,
    ]);

    // Create dummy backup files
    File::ensureDirectoryExists($this->backupDir);
    $oldBackup = $this->backupDir.'/mess_backup_2026_05_15_030000.sql.gz';
    $currentBackup = $this->backupDir.'/mess_backup_2026_10_01_030000.sql.gz';

    File::put($oldBackup, gzencode('-- Old dump', 9));
    File::put($currentBackup, gzencode('-- Current dump', 9));

    $this->artisan('messdb:cleanup')
        ->assertSuccessful()
        ->expectsOutputToContain('Successfully pruned 1 old backup file(s)');

    expect(File::exists($oldBackup))->toBeFalse()
        ->and(File::exists($currentBackup))->toBeTrue();
});

test('messdb:cleanup retains backups of recently closed months within 4-day grace window', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-03 12:00:00'));

    // Month 9 closed only 2 days ago (within 4-day grace buffer)
    $recentlyClosedMonth = Month::factory()->create([
        'year' => 2026,
        'month' => 9,
        'is_closed' => true,
        'closed_at' => Carbon::parse('2026-10-01 10:00:00'),
    ]);

    File::ensureDirectoryExists($this->backupDir);
    $recentBackup = $this->backupDir.'/mess_backup_2026_09_25_030000.sql.gz';
    File::put($recentBackup, gzencode('-- Recent month dump', 9));

    $this->artisan('messdb:cleanup')
        ->assertSuccessful()
        ->expectsOutputToContain('No old backup files met the criteria for deletion');

    expect(File::exists($recentBackup))->toBeTrue();
});
