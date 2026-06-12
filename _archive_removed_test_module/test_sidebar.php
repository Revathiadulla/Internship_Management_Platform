<?php
$_SERVER['SCRIPT_NAME'] = '/IMP/admin/dashboard.php';
$_SERVER['PHP_SELF'] = '/IMP/admin/dashboard.php';
ob_start();
include 'includes/admin_sidebar.php';
$out = ob_get_clean();
echo $out;
?>
