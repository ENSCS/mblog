<?php
// ตั้งค่า error reporting ตาม APP_ENV และดักข้อผิดพลาดที่ไม่คาดคิดทั้งหมด
// ให้ log ลงไฟล์ + โชว์หน้า error ที่เป็นมิตรแทนข้อความ error ดิบของ PHP
// require ไว้จาก config.php ซึ่งเป็นจุดโหลดแรกสุดของทุกหน้า จึงครอบคลุมทุก entry point

if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', LOG_DIR . 'php-error.log');
error_reporting(E_ALL);

// เรียกจากไหนก็ได้เพื่อโชว์หน้า error กลางแบบเดียวกันทุกหน้า เช่น
// renderErrorPage(404, 'ไม่พบบทความนี้');
function renderErrorPage(int $code, string $message): void
{
    if (!headers_sent()) {
        http_response_code($code);
    }
    $errorCode = $code;
    $errorMessage = $message;
    require __DIR__ . '/../error.php';
    exit;
}

// จับ error/warning/notice ทั้งหมด แค่ log ไว้ ไม่เปลี่ยนพฤติกรรมเดิมของ PHP
// (ปล่อยให้ทำงานต่อตามปกติ — ต่างจาก exception ที่ทำให้เว็บพังจริง)
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    error_log("PHP error [$severity]: $message in $file:$line");
    return false;
});

// จับข้อผิดพลาดร้ายแรงที่ไม่มีใครดักไว้ (เว็บกำลังจะพัง) — log แล้วโชว์หน้า 500 ที่เป็นมิตรแทน
set_exception_handler(function (Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    renderErrorPage(500, 'เซิร์ฟเวอร์มีปัญหาบางอย่าง ลองใหม่อีกครั้ง');
});
