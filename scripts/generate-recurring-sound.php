<?php

$sampleRate = 22050;
$duration = 0.18;
$freq = 880.0;
$volume = 0.18;
$numSamples = (int) ($sampleRate * $duration);
$data = '';

for ($i = 0; $i < $numSamples; $i++) {
    $t = $i / $sampleRate;
    $env = $t < 0.02 ? ($t / 0.02) : max(0.0, 1.0 - ($t / $duration));
    $sample = (int) round(sin(2 * M_PI * $freq * $t) * $volume * $env * 32767);
    $data .= pack('v', $sample & 0xFFFF);
}

$dataSize = strlen($data);
$hdr = pack('A4V', 'RIFF', 36 + $dataSize);
$hdr .= 'WAVE';
$hdr .= pack('A4VvvVVvv', 'fmt ', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16);
$hdr .= pack('A4V', 'data', $dataSize);

$dir = dirname(__DIR__).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'sounds';
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$path = $dir.DIRECTORY_SEPARATOR.'recurring-approval.wav';
file_put_contents($path, $hdr.$data);
echo 'wav='.filesize($path).PHP_EOL;
