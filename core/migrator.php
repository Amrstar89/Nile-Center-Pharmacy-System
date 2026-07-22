<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    NILE CENTER ERP v3.0 - DATABASE MIGRATOR                 ║
 * ║  CLI: php migrator.php [status|migrate|rollback|create] [name]           ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/tenant-router.php';

function migratorInit(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration` VARCHAR(255) NOT NULL,
            `batch` INT(11) NOT NULL DEFAULT 1,
            `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migratorGetExecuted(PDO $db): array {
    try {
        $stmt = $db->query("SELECT migration FROM schema_migrations ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { return []; }
}

function migratorGetNextBatch(PDO $db): int {
    try { return ((int) $db->query("SELECT MAX(batch) FROM schema_migrations")->fetchColumn()) + 1; }
    catch (PDOException $e) { return 1; }
}

function migratorGetFiles(string $migrationsDir): array {
    if (!is_dir($migrationsDir)) return [];
    $files = [];
    foreach (new DirectoryIterator($migrationsDir) as $file) {
        if ($file->isFile() && $file->getExtension() === 'sql') $files[] = $file->getFilename();
    }
    sort($files);
    return $files;
}

function migratorRun(PDO $db, string $migrationsDir): array {
    migratorInit($db);
    $executed = migratorGetExecuted($db);
    $files = migratorGetFiles($migrationsDir);
    $nextBatch = migratorGetNextBatch($db);
    $results = []; $executedCount = 0;

    foreach ($files as $file) {
        if (in_array($file, $executed)) { $results[] = ['status' => 'skipped', 'file' => $file]; continue; }
        $filepath = rtrim($migrationsDir, '/') . '/' . $file;
        $statements = migratorParseSQL(file_get_contents($filepath));
        try {
            $db->beginTransaction();
            foreach ($statements as $stmt) { if (trim($stmt)) $db->exec($stmt); }
            $db->prepare("INSERT INTO schema_migrations (migration, batch) VALUES (?, ?)")->execute([$file, $nextBatch]);
            $db->commit();
            $results[] = ['status' => 'executed', 'file' => $file];
            $executedCount++;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $results[] = ['status' => 'failed', 'file' => $file, 'error' => $e->getMessage()];
        }
    }

    return ['executed' => $executedCount, 'skipped' => count($files) - $executedCount - count(array_filter($results, fn($r) => $r['status'] === 'failed')), 'failed' => count(array_filter($results, fn($r) => $r['status'] === 'failed')), 'results' => $results];
}

function migratorParseSQL(string $sql): array {
    $statements = [];
    $delimiter = ';';
    $current = '';
    foreach (explode("\n", $sql) as $line) {
        $trimmed = trim($line);
        if (empty($trimmed) || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) continue;
        if (stripos($trimmed, 'DELIMITER') === 0) {
            $parts = preg_split('/\s+/', $trimmed);
            if (isset($parts[1])) {
                if (!empty($current)) { $statements[] = str_replace($delimiter, ';', $current); $current = ''; }
                $delimiter = trim($parts[1], " \t\n\r\0\x0B`");
            }
            continue;
        }
        $current .= $line . "\n";
        if (substr($trimmed, -strlen($delimiter)) === $delimiter) {
            $statement = rtrim(substr($statement ?? $current, 0, -strlen($delimiter)));
            if ($delimiter !== ';') $statement = str_replace($delimiter, ';', $current);
            else $statement = substr($current, 0, -strlen($delimiter));
            if (!empty(trim($statement))) $statements[] = $statement;
            $current = '';
        }
    }
    if (!empty(trim($current))) $statements[] = rtrim($current);
    return $statements;
}

function migratorStatus(PDO $db, string $migrationsDir): array {
    migratorInit($db);
    $executed = migratorGetExecuted($db);
    $files = migratorGetFiles($migrationsDir);
    $pending = array_diff($files, $executed);
    return ['total' => count($files), 'executed' => count($executed), 'pending' => count($pending), 'executed_migrations' => $executed, 'pending_migrations' => array_values($pending)];
}

function migratorCreate(string $migrationsDir, string $name): string {
    if (!is_dir($migrationsDir)) mkdir($migrationsDir, 0755, true);
    $filename = date('Y_m_d_His') . "_{$name}.sql";
    $filepath = rtrim($migrationsDir, '/') . '/' . $filename;
    file_put_contents($filepath, "-- Migration: {$name}\n-- Created: " . date('Y-m-d H:i:s') . "\n\n-- UP\n\n-- DOWN\n");
    return $filepath;
}

// ─── CLI Interface ──────────────────────────────────────────────────────────
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'status';
    $arg = $argv[2] ?? '';
    $migrationsDir = __DIR__ . '/../database/migrations';
    try { $db = getMasterDB(); } catch (Exception $e) { echo "Error: " . $e->getMessage() . "\n"; exit(1); }

    switch ($command) {
        case 'status':
            $status = migratorStatus($db, $migrationsDir);
            echo "Migration Status:\n  Total: {$status['total']}\n  Executed: {$status['executed']}\n  Pending: {$status['pending']}\n";
            break;
        case 'migrate':
            echo "Running migrations...\n";
            $result = migratorRun($db, $migrationsDir);
            echo "Executed: {$result['executed']}, Skipped: {$result['skipped']}, Failed: {$result['failed']}\n";
            break;
        case 'create':
            if (empty($arg)) { echo "Usage: php migrator.php create <name>\n"; exit(1); }
            echo "Created: " . migratorCreate($migrationsDir, $arg) . "\n";
            break;
        default:
            echo "Nile Center ERP - Database Migrator\nUsage: php migrator.php [status|migrate|create]\n";
    }
}
