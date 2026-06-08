<?php
/**
 * Micro-benchmark for hot paths in the Cloudinary PHP SDK.
 *
 * Usage:
 *   php tools/benchmark.php
 *
 * Run on each branch/stash to compare before/after.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Asset\Image;
use Cloudinary\Asset\Video;
use Cloudinary\Configuration\Configuration;

// ── Setup ────────────────────────────────────────────────────────────────────

putenv('CLOUDINARY_URL=cloudinary://api_key:api_secret@my_cloud');
Configuration::instance()->init();
Configuration::instance()->url->analytics(false);

const ITERATIONS = 10_000;
const RUNS       = 3;

// ── Benchmark helpers ─────────────────────────────────────────────────────────

function bench(string $label, callable $fn): void
{
    $times = [];

    for ($run = 0; $run < RUNS; $run++) {
        $start = hrtime(true);
        for ($i = 0; $i < ITERATIONS; $i++) {
            $fn();
        }
        $times[] = (hrtime(true) - $start) / 1e6; // ns → ms
    }

    $avg = array_sum($times) / count($times);
    $min = min($times);
    $max = max($times);

    printf(
        "  %-50s  avg: %7.2f ms  min: %7.2f ms  max: %7.2f ms\n",
        $label,
        $avg,
        $min,
        $max
    );
}

// ── Scenarios ─────────────────────────────────────────────────────────────────

echo str_repeat('─', 90) . "\n";
echo sprintf("  Cloudinary PHP SDK benchmark — %d iterations × %d runs\n", ITERATIONS, RUNS);
echo str_repeat('─', 90) . "\n";

// 1. Asset construction (exercises configuration() fast path)
bench('new Image($source)', function () {
    $img = new Image('sample/image.jpg');
});

// 2. Asset construction + URL generation (exercises finalizeSource, finalizeVersion)
bench('(string) new Image($source)', function () {
    $img = (string) new Image('sample/image.jpg');
});

// 3. URL generation on a pre-built asset (isolates toUrl() overhead)
$image = new Image('sample/image.jpg');
bench('$image->toUrl()  [pre-built asset]', function () use ($image) {
    $url = (string) $image->toUrl();
});

// 4. Asset with suffix (exercises setSuffix + finalizeAssetType)
bench('new Image + setSuffix()', function () {
    $img = new Image('sample/image.jpg');
    $img->asset->suffix = 'my-seo-name';
    $url = (string) $img->toUrl();
});

// 5. Video asset (different asset type path)
bench('(string) new Video($source)', function () {
    $img = (string) new Video('sample/video.mp4');
});

// 6. Configuration::jsonSerialize() (exercises array_merge → += fix)
$config = Configuration::instance();
bench('Configuration::jsonSerialize()', function () use ($config) {
    $config->jsonSerialize();
});

// 7. Asset construction from Configuration array (slow path, for comparison)
$configArray = $config->jsonSerialize();
bench('new Image($source, $configArray)  [array config]', function () use ($configArray) {
    $img = new Image('sample/image.jpg', $configArray);
});

echo str_repeat('─', 90) . "\n";
