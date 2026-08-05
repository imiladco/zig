<?php
/**
 * اجراکنندهٔ تست‌ها.
 *
 *     php tests/run.php          همه
 *     php tests/run.php svg      فقط گروهی که نامش svg دارد
 */

require_once __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';

foreach (glob(__DIR__ . '/*-test.php') as $file) {
    if ('' !== $filter && false === strpos(basename($file), $filter)) {
        continue;
    }

    require_once $file;
}

exit(Tests::summary());
