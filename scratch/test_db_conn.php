<?php
try {
    $c = mysqli_connect('by7xxebmaxfwobqrh1ne-mysql.services.clever-cloud.com', 'ujebqn1hlk9qd98k', 'zqPIiSbk9EU6l3KHrvml', 'by7xxebmaxfwobqrh1ne', 3306);
    if ($c) {
        echo "Successfully connected to Clever Cloud database!\n";
        mysqli_close($c);
    } else {
        echo "Failed to connect: " . mysqli_connect_error() . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
