<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Scheduled Cold Archive Cron Runner
|--------------------------------------------------------------------------
|
| File      : cron/audit_archive_cron.php
| Purpose   : Automated Scheduled Cron Job for moving audit logs older than
|             180 days (or configured threshold) from hot active table
|             `audit_deleted_records` to cold archive table
|             `audit_deleted_records_cold_archive`.
|
| Execution : php cron/audit_archive_cron.php --days=180
| Crontab   : 0 2 * * * php /path/to/cron/audit_archive_cron.php --days=180 >> /var/log/netfusion_audit_cron.log 2>&1
| Task Sched: Runs daily at 02:00 AM.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| License   : MIT
| Version   : 1.5.3
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../core/bootstrap.php';

use NetFusion\App\Services\ModuleDataRepository;

// Ensure Asia/Dhaka Timezone
date_default_timezone_set('Asia/Dhaka');
$startTime = microtime(true);
$runDate = date('Y-m-d H:i:s');

echo "====================================================================\n";
echo "   NETFUSION AUDIT COLD ARCHIVE CRON RUNNER — [{$runDate}]\n";
echo "====================================================================\n";

// Parse CLI options
$options = getopt('', ['days::', 'help']);

if (isset($options['help'])) {
    echo "Usage: php cron/audit_archive_cron.php [--days=180]\n";
    echo "Options:\n";
    echo "  --days=N   Retention threshold in days (Default: 180 days)\n";
    exit(0);
}

$retentionDays = isset($options['days']) && is_numeric($options['days']) ? (int) $options['days'] : 180;
if ($retentionDays <= 0) {
    $retentionDays = 180;
}

echo "▶ Retention Policy Threshold: {$retentionDays} Days\n";
echo "▶ Source Table: `audit_deleted_records`\n";
echo "▶ Cold Storage Table: `audit_deleted_records_cold_archive`\n";
echo "▶ Running Transactional Migration...\n";

try {
    $result = ModuleDataRepository::archiveColdAuditRecords($retentionDays, 'system_scheduled_cron');

    $duration = round((microtime(true) - $startTime) * 1000, 2);

    if ($result['success']) {
        echo "✅ SUCCESS: " . $result['message'] . "\n";
        echo "  - Archived Records Count : " . $result['archived_count'] . "\n";
        echo "  - Cutoff Date (Older Than): " . $result['threshold_date'] . "\n";
        echo "  - Operator Identity      : " . $result['archived_by'] . "\n";
        echo "  - Execution Duration     : {$duration} ms\n";
        echo "====================================================================\n\n";
        exit(0);
    } else {
        echo "❌ FAILED: " . $result['message'] . "\n";
        echo "====================================================================\n\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "❌ FATAL EXCEPTION: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
    echo "====================================================================\n\n";
    exit(1);
}
