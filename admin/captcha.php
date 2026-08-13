<?php
/**
 * 图形验证码生成
 */
require_once __DIR__ . '/../core/bootstrap.php';

if (!isInstalled()) {
    exit;
}

Security::initSession();

// 生成4位验证码
$code = '';
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
for ($i = 0; $i < 4; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}
$_SESSION['admin_captcha'] = $code;

// 生成验证码图片
$width = 120;
$height = 40;
$image = imagecreatetruecolor($width, $height);

// 背景
$bgColor = imagecolorallocate($image, 245, 247, 255);
imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

// 干扰点
for ($i = 0; $i < 80; $i++) {
    $color = imagecolorallocate($image, random_int(150, 220), random_int(150, 220), random_int(150, 220));
    imagesetpixel($image, random_int(0, $width), random_int(0, $height), $color);
}

// 干扰线
for ($i = 0; $i < 3; $i++) {
    $color = imagecolorallocate($image, random_int(100, 200), random_int(100, 200), random_int(200, 255));
    imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $color);
}

// 文字
$colors = [
    imagecolorallocate($image, 102, 126, 234),
    imagecolorallocate($image, 118, 75, 162),
    imagecolorallocate($image, 50, 50, 150),
    imagecolorallocate($image, 80, 60, 180),
];
$charWidth = $width / (strlen($code) + 1);
for ($i = 0; $i < strlen($code); $i++) {
    $angle = random_int(-15, 15);
    $size = 18;
    $x = $charWidth * ($i + 0.7);
    $y = random_int(26, 32);
    // 使用内置字体绘制（无需TTF文件）
    imagestring($image, 5, (int)$x, (int)($y - 15), $code[$i], $colors[$i % count($colors)]);
}

// 输出
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
imagepng($image);
imagedestroy($image);
