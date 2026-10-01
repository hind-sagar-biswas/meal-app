<?php

namespace App\Services;

use App\Models\Month;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BackupService
{
    /**
     * Directory path where database backups are stored relative to storage/app.
     */
    public const BACKUP_DIRECTORY = 'backups';

    /**
     * Generate and save a gzipped SQL dump of the database.
     *
     * @return array{path: string, filename: string, size_bytes: int, created_at: string}
     */
    public function createBackup(?string $customFilename = null): array
    {
        $dumpSql = $this->generateSqlDump();
        $gzippedContent = gzencode($dumpSql, 9);

        $now = now();
        $filename = $customFilename ?: sprintf('mess_backup_%s.sql.gz', $now->format('Y_m_d_His'));
        $relativeDir = self::BACKUP_DIRECTORY;
        $relativePath = $relativeDir.'/'.$filename;

        // Ensure backup directory exists in storage
        $absoluteDir = storage_path('app/'.$relativeDir);
        if (! File::exists($absoluteDir)) {
            File::makeDirectory($absoluteDir, 0755, true);
        }

        $absolutePath = storage_path('app/'.$relativePath);
        File::put($absolutePath, $gzippedContent);

        return [
            'path' => $absolutePath,
            'relative_path' => $relativePath,
            'filename' => $filename,
            'size_bytes' => (int) filesize($absolutePath),
            'created_at' => $now->toIso8601String(),
        ];
    }

    /**
     * Prune backups of closed months where closed_at is older than $graceDays.
     *
     * @return array{deleted_files: array<int, string>, count: int}
     */
    public function cleanupOldBackups(int $graceDays = 4): array
    {
        $backupDir = storage_path('app/'.self::BACKUP_DIRECTORY);
        if (! File::exists($backupDir)) {
            return ['deleted_files' => [], 'count' => 0];
        }

        // Find all closed months where closed_at is at least $graceDays old
        $closedMonths = Month::where('is_closed', true)
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', now()->subDays($graceDays))
            ->get();

        if ($closedMonths->isEmpty()) {
            return ['deleted_files' => [], 'count' => 0];
        }

        // Build list of eligible (year, month) pairs for closed months
        $eligibleMonthMap = [];
        foreach ($closedMonths as $m) {
            $key = sprintf('%04d_%02d', $m->year, $m->month);
            $eligibleMonthMap[$key] = true;
        }

        $files = File::files($backupDir);
        $deleted = [];

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Match format mess_backup_YYYY_MM_DD_HHmmss.sql.gz
            if (preg_match('/^mess_backup_(\d{4})_(\d{2})_(\d{2})_(\d{6})\.sql\.gz$/', $filename, $matches)) {
                $year = (int) $matches[1];
                $month = (int) $matches[2];
                $monthKey = sprintf('%04d_%02d', $year, $month);

                if (isset($eligibleMonthMap[$monthKey])) {
                    File::delete($file->getPathname());
                    $deleted[] = $filename;
                }
            }
        }

        return [
            'deleted_files' => $deleted,
            'count' => count($deleted),
        ];
    }

    /**
     * Generate SQL dump string for current database connection.
     */
    public function generateSqlDump(): string
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();

        $tables = $this->getTables($pdo, $driver);

        $output = "-- MessApp Database Backup\n";
        $output .= '-- Generated at: '.now()->toIso8601String()."\n";
        $output .= "-- Driver: {$driver}\n\n";

        if ($driver === 'mysql') {
            $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        }

        foreach ($tables as $table) {
            $output .= $this->dumpTableStructure($pdo, $driver, $table);
            $output .= $this->dumpTableData($pdo, $driver, $table);
        }

        if ($driver === 'mysql') {
            $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        }

        return $output;
    }

    /**
     * Get list of base tables for the database.
     *
     * @return array<int, string>
     */
    protected function getTables(\PDO $pdo, string $driver): array
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        // MySQL / MariaDB
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        return array_map(fn ($row) => $row[0], $rows);
    }

    /**
     * Generate DDL structure for a single table.
     */
    protected function dumpTableStructure(\PDO $pdo, string $driver, string $table): string
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'");
            $sql = $stmt->fetchColumn();

            return "DROP TABLE IF EXISTS \"{$table}\";\n{$sql};\n\n";
        }

        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(\PDO::FETCH_NUM);
        $sql = $row[1] ?? '';

        return "DROP TABLE IF EXISTS `{$table}`;\n{$sql};\n\n";
    }

    /**
     * Dump all table data as INSERT statements.
     */
    protected function dumpTableData(\PDO $pdo, string $driver, string $table): string
    {
        $tableIdentifier = $driver === 'sqlite' ? "\"{$table}\"" : "`{$table}`";
        $stmt = $pdo->query("SELECT * FROM {$tableIdentifier}");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return '';
        }

        $columnNames = array_keys($rows[0]);
        $quotedColumns = array_map(fn ($col) => $driver === 'sqlite' ? "\"{$col}\"" : "`{$col}`", $columnNames);
        $columnsStr = implode(', ', $quotedColumns);

        $output = '';
        foreach ($rows as $row) {
            $values = array_map(function ($val) use ($pdo) {
                if ($val === null) {
                    return 'NULL';
                }

                return $pdo->quote((string) $val);
            }, array_values($row));

            $valuesStr = implode(', ', $values);
            $output .= "INSERT INTO {$tableIdentifier} ({$columnsStr}) VALUES ({$valuesStr});\n";
        }

        return $output."\n";
    }
}
