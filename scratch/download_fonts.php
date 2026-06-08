<?php
$fonts = [
    'helvetica.json',
    'helveticab.json',
    'helveticai.json',
    'helveticabi.json',
    'times.json',
    'timesb.json',
    'timesi.json',
    'timesbi.json',
    'courier.json',
    'courierb.json',
    'courieri.json',
    'courierbi.json',
    'symbol.json',
    'zapfdingbats.json'
];

$dest_dir = __DIR__ . '/../includes/font';
if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0777, true);
}

foreach ($fonts as $font) {
    $url = "https://raw.githubusercontent.com/jung-kurt/gofpdf/master/font/" . $font;
    echo "Downloading $font...\n";
    $content = file_get_contents($url);
    if ($content === false) {
        echo "Failed to download $font\n";
    } else {
        file_put_contents($dest_dir . '/' . $font, $content);
        echo "Saved $font successfully.\n";
    }
}
echo "All done!\n";
