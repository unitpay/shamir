<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Unitpay\Shamir\Shamir;

$secret = 'Some super secret';
$parts = 5;
$threshold = 3;
$shares = Shamir::split($secret, $parts, $threshold);

echo "Base64 encoded shares:\n";

foreach ($shares as $idx => $share) {
    echo $idx . ': ' . base64_encode($share) . "\n";
}

shuffle($shares);
$recovered = Shamir::reconstruct(array_slice($shares, 0, $threshold));

echo "\nRecovered string: $recovered\n";
