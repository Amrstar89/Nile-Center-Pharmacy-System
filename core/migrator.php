<?php
/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    NILE CENTER ERP v3.0 - DATABASE MIGRATOR                 ║
 * ║                                                                              ║
 * ║  Versioned database migration system for tenant databases                    ║
 * ║  Usage: php migrator.php [status|migrate|rollback|create] [name]           ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/tenant-router.php';

// Migration table SQL
$MIGRATION_TABLE_SQL = "
    CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration` VARCHAR(255) NOT NULL,
        `batch` INT(11) NOT NULL DEFAULT 1,
        `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

/**
 * Initialize migrations table
 */
function migratorInit(PDO $db): void {
    global $MIGRATION_TABLE_SQL;
    $db->exec($MIGRATION_TABLE_SQL);
}

/**
 * Get list of executed migrations
 */
function migratorGetExecuted(PDO $db): array {
    try {
        $stmt = $db->query("SELECT migration FROM schema_migrations ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return []; // Table doesn't exist yet
    }
}

/**
 * Get next batch number
 */
function migratorGetNextBatch(PDO $db): int {
    try {
        $stmt = $db->query("SELECT MAX(batch) FROM schema_migrations");
        return ((int) $stmt->fetchColumn()) + 1;
    } catch (PDOException $e) {
        return 1;
    }
}

/**
 * Get migration files from directory
 */
function migratorGetFiles(string $migrationsDir): array {
    if (!is_dir($migrationsDir)) {
        return [];
    }
    
    $files = [];
    $iterator = new DirectoryIterator($migrationsDir);
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'sql') {
            $files[] = $file->getFilename();
        }
    }
    sort($files);
    return $files;
}

/**
 * Run migrations on a database
 * 
 * @param PDO $db Target database
 * @param string $migrationsDir Directory containing .sql migration files
 * @return array Results
 */
function migratorRun(PDO $db, string $migrationsDir): array {
    migratorInit($db);
    
    $executed = migratorGetExecuted($db);
    $files = migratorGetFiles($migrationsDir);
    $nextBatch = migratorGetNextBatch($db);
    
    $results = [];
    $executedCount = 0;
    
    foreach ($files as $file) {
        if (in_array($file, $executed)) {
            $results[] = ['status' => 'skipped', 'file' => $file];
            continue;
        }
        
        $filepath = rtrim($migrationsDir, '/') . '/' . $file;
        $sql = file_get_contents($filepath);
        
        try {
            // Split by ";" but be careful with DELIMITER statements
            $statements = migratorParseSQL($sql);
            
            $db->beginTransaction();
            foreach ($statements as $statement) {
                if (trim($statement)) {
                    $db->exec($statement);
                }
            }
            
            // Record migration
            $stmt = $db->prepare("INSERT INTO schema_migrations (migration, batch) VALUES (?, ?)");
            $stmt->execute([$file, $nextBatch]);
            
            $db->commit();
            $results[] = ['status' => 'executed', 'file' => $file];
            $executedCount++;
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $results[] = ['status' => 'failed', 'file' => $file, 'error' => $e->getMessage()];
        }
    }
    
    return [
        'executed' => $executedCount,
        'skipped' => count($files) - $executedCount - count(array_filter($results, fn($r) => $r['status'] === 'failed')),
        'failed' => count(array_filter($results, fn($r) => $r['status'] === 'failed')),
        'results' => $results,
    ];
}

/**
 * Parse SQL file, handling DELIMITER changes
 */
function migratorParseSQL(string $sql): array {
    $statements = [];
    $delimiter = ';';
    $current = '';
    
    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Skip comments and empty lines
        if (empty($trimmed) || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
            continue;
        }
        
        // Handle DELIMITER directive
        if (stripos($trimmed, 'DELIMITER') === 0) {
            $parts = preg_split('/\s+/', $trimmed);
            if (isset($parts[1])) {
                // Save current statement before changing delimiter
                if (!empty($current)) {
                    $statements[] = str_replace($delimiter, ';', $current);
                    $current = '';
                }
                $delimiter = trim($parts[1], " \t\n\r\0\x0B`");
            }
            continue;
        }
        
        $current .= $line . "\n";
        
        // Check if statement is complete
        if (substr($trimmed, -strlen($delimiter)) === $delimiter) {
            $statement = rtrim($current);
            // Remove trailing delimiter
            $statement = substr($statement, 0, -strlen($delimiter));
            if (!empty(trim($statement))) {
                // Convert back to standard ; delimiter for execution
                if ($delimiter !== ';') {
                    $statement = str_replace($delimiter, ';', $statement);
                }
                $statements[] = $statement;
            }
            $current = '';
        }
    }
    
    // Catch any remaining statement
    if (!empty(trim($current))) {
        $statements[] = rtrim($current);
    }
    
    return $statements;
}

/**
 * Rollback last batch
 */
function migratorRollback(PDO $db, string $migrationsDir): array {
    try {
        $stmt = $db->query("SELECT MAX(batch) as max_batch FROM schema_migrations");
        $maxBatch = (int) $stmt->fetchColumn();
        
        if ($maxBatch <= 0) {
            return ['status' => 'info', 'message' => 'No migrations to rollback'];
        }
        
        $stmt = $db->prepare("SELECT migration FROM schema_migrations WHERE batch = ? ORDER BY id DESC");
        $stmt->execute([$maxBatch]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Note: Full rollback would need down() migrations
        // For now, we just remove the migration records
        $stmt = $db->prepare("DELETE FROM schema_migrations WHERE batch = ?");
        $stmt->execute([$maxBatch]);
        
        return [
            'status' => 'rolled_back',
            'batch' => $maxBatch,
            'migrations' => $migrations,
            'note' => 'Migration records removed. Manual schema changes may need to be reverted.',
        ];
        
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

/**
 * Show migration status
 */
function migratorStatus(PDO $db, string $migrationsDir): array {
    migratorInit($db);
    
    $executed = migratorGetExecuted($db);
    $files = migratorGetFiles($migrationsDir);
    
    $pending = array_diff($files, $executed);
    
    return [
        'total' => count($files),
        'executed' => count($executed),
        'pending' => count($pending),
        'executed_migrations' => $executed,
        'pending_migrations' => array_values($pending),
    ];
}

/**
 * Create a new migration file
 */
function migratorCreate(string $migrationsDir, string $name): string {
    if (!is_dir($migrationsDir)) {
        mkdir($migrationsDir, 0755, true);
    }
    
    $timestamp = date('Y_m_d_His');
    $filename = "{$timestamp}_{$name}.sql";
    $filepath = rtrim($migrationsDir, '/') . '/' . $filename;
    
    $template = "-- Migration: {$name}\n";
    $template .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
    $template .= "\n";
    $template .= "-- UP (Apply this migration)\n";
    $template .= "\n";
    $template .= "-- Add your SQL here\n";
    $template .= "\n";
    $template .= "-- DOWN (Rollback - optional)\n";
    $template .= "\n";
    
    file_put_contents($filepath, $template);
    return $filepath;
}

// ─── CLI Interface ──────────────────────────────────────────────────────────

if (php_sapi_name() === 'cli') {
    $scriptName = $argv[0] ?? 'migrator.php';
    $command = $argv[1] ?? 'status';
    $arg = $argv[2] ?? '';
    
    $migrationsDir = __DIR__ . '/../database/migrations';
    
    // Try to connect to master DB for CLI usage
    try {
        $db = getMasterDB();
    } catch (Exception $e) {
        echo "Error: Cannot connect to master database. " . $e->getMessage() . "\n";
        exit(1);
    }
    
    switch ($command) {
        case 'status':
            $status = migratorStatus($db, $migrationsDir);
            echo "Migration Status:\n";
            echo "  Total: {$status['total']}\n";
            echo "  Executed: {$status['executed']}\n";
            echo "  Pending: {$status['pending']}\n";
            if (!empty($status['pending_migrations'])) {
                echo "\nPending migrations:\n";
                foreach ($status['pending_migrations'] as $m) {
                    echo "  [ ] {$m}\n";
                }
            }
            break;
            
        case 'migrate':
            echo "Running migrations...\n";
            $result = migratorRun($db, $migrationsDir);
            echo "Executed: {$result['executed']}, Skipped: {$result['skipped']}, Failed: {$result['failed']}\n";
            foreach ($result['results'] as $r) {
                $icon = $r['status'] === 'executed' ? '✓' : ($r['status'] === 'failed' ? '✗' : '-');
                echo "  [{$icon}] {$r['file']}";
                if (isset($r['error'])) {
                    echo " - {$r['error']}";
                }
                echo "\n";
            }
            break;
            
        case 'rollback':
            echo "Rolling back last batch...\n";
            $result = migratorRollback($db, $migrationsDir);
            echo "Status: {$result['status']}\n";
            if (isset($result['migrations'])) {
                foreach ($result['migrations'] as $m) {
                    echo "  - {$m}\n";
                }
            }
            if (isset($result['note'])) {
                echo "Note: {$result['note']}\n";
            }
            break;
            
        case 'create':
            if (empty($arg)) {
                echo "Usage: php {$scriptName} create <migration_name>\n";
                exit(1);
            }
            $filepath = migratorCreate($migrationsDir, $arg);
            echo "Created migration: {$filepath}\n";
            break;
            
        default:
            echo "Nile Center ERP - Database Migrator\n";
            echo "Usage: php {$scriptName} [command] [options]\n\n";
            echo "Commands:\n";
            echo "  status              Show migration status\n";
            echo "  migrate             Run pending migrations\n";
            echo "  rollback            Rollback last batch\n";
            echo "  create <name>       Create a new migration file\n";
            break;
    }
}
