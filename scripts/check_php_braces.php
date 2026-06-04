<?php
$f = __DIR__ . '/../coordinator_generate_test.php';
$content = file_get_contents($f);
$php_segments = [];
$offset = 0;
while (($start = strpos($content, '<?php', $offset)) !== false) {
    $end = strpos($content, '?>', $start);
    if ($end === false) {
        $php_segments[] = substr($content, $start+5);
        break;
    } else {
        $php_segments[] = substr($content, $start+5, $end - ($start+5));
        $offset = $end + 2;
    }
}
$total_open = 0; $total_close = 0;
$lines_all = explode("\n", $content);
foreach ($php_segments as $seg) {
    $total_open += substr_count($seg, '{');
    $total_close += substr_count($seg, '}');
}
echo "php_open:$total_open php_close:$total_close\n";
// For more detail: print segment with maxdepth
$maxdepth = 0; $maxline = 0; $curdepth = 0; $lineno = 1;
$php_content = implode("\n", $php_segments);
$php_lines = explode("\n", $php_content);
foreach ($php_lines as $i => $l) {
    $curdepth += substr_count($l, '{');
    $curdepth -= substr_count($l, '}');
    if ($curdepth > $maxdepth) { $maxdepth = $curdepth; $maxline = $i+1; }
}
echo "maxdepth_in_php={$maxdepth} at php-line={$maxline}\n";
// Show few lines around maxline
$start = max(1, $maxline - 6);
$end = min(count($php_lines), $maxline + 6);
for ($i=$start;$i<=$end;$i++) {
    $num = str_pad($i,4,' ',STR_PAD_LEFT);
    echo "$num: " . ($php_lines[$i-1] ?? '') . "\n";
}
// Find last php line where depth > 0 during scan
$depth = 0;
$lastPos = 0;
foreach ($php_lines as $i => $l) {
    $depth += substr_count($l, '{');
    $depth -= substr_count($l, '}');
    if ($depth > 0) $lastPos = $i+1;
}
if ($lastPos) {
    echo "last non-zero php depth at php-line={$lastPos}\n";
    $s = max(1, $lastPos - 6);
    $e = min(count($php_lines), $lastPos + 6);
    for ($i=$s;$i<=$e;$i++) {
        $num = str_pad($i,4,' ',STR_PAD_LEFT);
        echo "$num: " . ($php_lines[$i-1] ?? '') . "\n";
    }
}

// Print per-PHP-segment open/close counts and cumulative depth after each segment
$cum = 0; $segIndex = 0; $offsetLines = 0;
foreach ($php_segments as $seg) {
    $segIndex++;
    $open = substr_count($seg, '{');
    $close = substr_count($seg, '}');
    $cum += $open - $close;
    echo "SEG#{$segIndex}: open={$open} close={$close} cumDepth={$cum}\n";
}

// Inspect the first large PHP segment line-by-line to find the exact unclosed brace
if (count($php_segments) > 0) {
    echo "\n-- First PHP segment depth trace --\n";
    $lines = explode("\n", $php_segments[0]);
    $d = 0;
    foreach ($lines as $i => $l) {
        $o = substr_count($l, '{');
        $c = substr_count($l, '}');
        $d += $o - $c;
        if ($o>0 || $c>0) {
            $num = str_pad($i+1,4,' ',STR_PAD_LEFT);
            echo "$num depth=$d opens=$o closes=$c | " . rtrim($l) . "\n";
        }
    }
    echo "Final depth in first segment=$d\n";
}

// Quick scan: lines with odd counts of quotes in first segment (possible unterminated strings)
if (count($php_segments) > 0) {
    echo "\n-- Quote parity scan in first segment --\n";
    $lines = explode("\n", $php_segments[0]);
    foreach ($lines as $i => $l) {
        $dq = substr_count($l, '"');
        $sq = substr_count($l, "'");
        if (($dq % 2) !== 0 || ($sq % 2) !== 0) {
            $num = str_pad($i+1,4,' ',STR_PAD_LEFT);
            echo "$num dq=$dq sq=$sq | " . rtrim($l) . "\n";
        }
    }
}
