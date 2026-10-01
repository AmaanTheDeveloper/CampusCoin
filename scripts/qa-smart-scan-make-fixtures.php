<?php

$fix = __DIR__.'/fixtures';
if (! is_dir($fix)) {
    mkdir($fix, 0777, true);
}

// Receipt OCR fixture
$img = imagecreatetruecolor(600, 400);
$w = imagecolorallocate($img, 255, 255, 255);
$b = imagecolorallocate($img, 0, 0, 0);
imagefilledrectangle($img, 0, 0, 599, 399, $w);
imagestring($img, 5, 40, 40, 'Campus Cafe', $b);
imagestring($img, 5, 40, 80, 'Date: 2026-09-10', $b);
imagestring($img, 5, 40, 180, 'Total Rs 42.00', $b);
imagepng($img, $fix.'/qa-receipt-ocr.png');
imagedestroy($img);

// Card OCR fixture (text for Tesseract; may still fail client geometry validator)
$img = imagecreatetruecolor(640, 400);
$bg = imagecolorallocate($img, 30, 30, 40);
$fg = imagecolorallocate($img, 220, 220, 230);
$card = imagecolorallocate($img, 55, 70, 120);
imagefilledrectangle($img, 0, 0, 639, 399, $bg);
imagefilledrectangle($img, 70, 90, 570, 310, $card);
imagestring($img, 5, 100, 130, 'VISA DEBIT', $fg);
imagestring($img, 5, 100, 180, '4532 1199 8877 4521', $fg);
imagestring($img, 5, 100, 230, 'VALID THRU 09/28', $fg);
imagestring($img, 5, 100, 270, 'CVV 123', $fg);
imagestring($img, 5, 350, 270, 'JANE STUDENT', $fg);
imagepng($img, $fix.'/qa-card-ocr.png');
imagedestroy($img);

echo "fixtures_ok\n";
