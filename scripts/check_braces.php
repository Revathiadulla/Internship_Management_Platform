<?php
$f = __DIR__ . '/../coordinator_generate_test.php';
$lines = file($f);
$depth = 0; $maxdepth = 0; $maxline = 0;
foreach ($lines as $ln => $line) {
    $chars = str_split($line);
    foreach ($chars as $c) {
        if ($c === '{') $depth++;
        if ($c === '}') $depth--;
    }
    if ($depth > $maxdepth) { $maxdepth = $depth; $maxline = $ln + 1; }
}
echo "total_open:" . substr_count(file_get_contents($f), '{') . " total_close:" . substr_count(file_get_contents($f), '}') . "\n";
echo "maxdepth={$maxdepth} at line={$maxline}\n";

// Show 10 lines around maxline
$start = max(1, $maxline - 6);
$end = min(count($lines), $maxline + 6);
for ($i = $start; $i <= $end; $i++) {
    $num = str_pad($i, 4, ' ', STR_PAD_LEFT);
    echo "$num: " . rtrim($lines[$i-1]);
}
