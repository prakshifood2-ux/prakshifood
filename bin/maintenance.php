<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$flag = dirname(__DIR__).'/storage/maintenance.flag';
$action = $argv[1] ?? 'status';
if (!in_array($action, ['on', 'off', 'status'], true)) {
    fwrite(STDERR, "Usage: php bin/maintenance.php [on|off|status]\n");
    exit(1);
}
if ($action === 'on' && file_put_contents($flag, gmdate('c').PHP_EOL, LOCK_EX) === false) {
    fwrite(STDERR, "Could not enable maintenance mode. Check storage permissions.\n");
    exit(1);
}
if ($action === 'off' && is_file($flag) && !unlink($flag)) {
    fwrite(STDERR, "Could not disable maintenance mode. Check storage permissions.\n");
    exit(1);
}
clearstatcache(true, $flag);
echo 'Maintenance mode is '.(is_file($flag) ? 'ON' : 'OFF').'.'.PHP_EOL;
