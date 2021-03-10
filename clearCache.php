#!/usr/bin/php
<?php
$sizeLimit = $argv[1] ?? 1024 * 1024 * 512; // 512 MB
$cacheDir = $argv[2] ?? __DIR__ . '/cache';
echo "Assuring $cacheDir is not bigger than $sizeLimit bytes\n";

$mtimes = $sizes = [];
scan($cacheDir, $mtimes, $sizes);
arsort($mtimes);
$totalSize = 0;
foreach ($mtimes as $path => $mtime) {
    $totalSize += $sizes[$path];
    if ($totalSize > $sizeLimit) {
        unlink($path);
        echo "removing $path \n";
    }
}

function scan(string $dir, array &$mtimes, array &$sizes): void {
    foreach (scandir($dir) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            scan($path, $mtimes, $sizes);
        } else {
            $mtimes[$path] = filemtime($path);
            $sizes[$path] = filesize($path);
        }
    }
}
