<?php
$f = __DIR__ . '/../coordinator_generate_test.php';
$lines = file($f);
$depth = 0;
for ($i=0;$i<count($lines);$i++){
    $line = $lines[$i];
    $open = substr_count($line,'{');
    $close = substr_count($line,'}');
    $depth += $open - $close;
    if ($open>0 || $close>0 || $depth>0) {
        $num = str_pad($i+1,4,' ',STR_PAD_LEFT);
        echo "$num depth=$depth opens=$open closes=$close | " . rtrim($line);
    }
}
echo "FINAL depth=$depth\n";
