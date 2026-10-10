<?php

if (isset($argv[1])) {
    $file = $argv[1];
} else {
    fwrite(STDERR, 'NO_FILE');
    exit(4);
}

if (!is_readable($file)) {
    fwrite(STDERR, 'UNREADABLE');
    exit(5);
}

include $file;
fwrite(STDERR, 'GUARD_NOT_TRIGGERED');
exit(2);
