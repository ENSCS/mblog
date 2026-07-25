<?php
// สคริปต์ backup ข้อมูล — บีบอัด articles/ + uploads/ ลง backups/backup-YYYYMMDD-HHMMSS.zip
// ไม่รวม config.php/credentials ในนี้ — ควรเก็บสำเนาไว้แยกต่างหากเอง (เช่น password manager)
// ไม่ควรปนกับ backup ทั่วไปที่อาจส่งต่อ/แชร์กัน
//
// รันเอง:  php scripts/backup.php
//
// ตั้งให้รันอัตโนมัติทุกคืน (ยังไม่ได้ตั้งให้ — เพิ่มบรรทัดนี้เองใน crontab ถ้าต้องการ):
//   0 2 * * * /usr/bin/php /path/to/mblog/scripts/backup.php >> /path/to/mblog/logs/backup.log 2>&1
//
// backup ที่ได้ยังอยู่บนเครื่อง/เซิร์ฟเวอร์เดียวกัน — ตามหลัก 3-2-1 ควรคัดลอกออกไปเก็บที่อื่นด้วย
// (คนละดิสก์ / cloud storage) เป็นขั้นตอนที่ต้องทำเพิ่มเอง สคริปต์นี้ยังไม่ได้ส่งออกให้อัตโนมัติ

require __DIR__ . '/../config.php';

const RETENTION_DAYS = 14;

function backupDirsInto(string $zipPath, array $sourceDirs, string $projectRoot): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Could not create zip file: $zipPath");
    }

    foreach ($sourceDirs as $dir) {
        $fullDir = $projectRoot . '/' . $dir;
        if (!is_dir($fullDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $localPath = $dir . '/' . substr($file->getPathname(), strlen($fullDir) + 1);
            $zip->addFile($file->getPathname(), $localPath);
        }
    }

    $zip->close();
}

if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}

$projectRoot = dirname(__DIR__);
$zipPath = BACKUP_DIR . 'backup-' . date('Ymd-His') . '.zip';

backupDirsInto($zipPath, ['articles', 'uploads'], $projectRoot);

$sizeKb = round(filesize($zipPath) / 1024, 1);
echo "Backup created: {$zipPath} ({$sizeKb} KB)\n";

// --- retention: ลบ backup เก่ากว่า RETENTION_DAYS วัน ---
$cutoff = time() - (RETENTION_DAYS * 86400);
$deleted = 0;
foreach (glob(BACKUP_DIR . 'backup-*.zip') as $file) {
    if (filemtime($file) < $cutoff) {
        unlink($file);
        $deleted++;
    }
}
if ($deleted > 0) {
    echo "Removed {$deleted} backup(s) older than " . RETENTION_DAYS . " days\n";
}
