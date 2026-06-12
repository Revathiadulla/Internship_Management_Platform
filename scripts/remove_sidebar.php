<?php
$files = glob('coordinator_*.php');
foreach($files as $f) {
  if($f == 'coordinator_student_reports.php') continue;
  $c = file_get_contents($f);
  $c = preg_replace('/<a href="coordinator_student_reports\.php".*?<\/a>\s*/s', '', $c);
  file_put_contents($f, $c);
}
echo "Done sidebars";
?>
