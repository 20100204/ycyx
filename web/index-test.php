<?php

$pics = "http://yunwei.ycypsz.com/upload/imgs/20180311/lb.jpg";
//header('Content-Type: image/png');
// 获取新的尺寸
list($width, $height) = getimagesize($pics);

// 重新取样
$image_p = imagecreatetruecolor(110, 110);

$image = imagecreatefromjpeg($pics);
imagecopyresampled($image_p, $image, 0, 0, 0, 0, 110, 110, $width, $height);

// 输出
imagejpeg($image_p, "test.jpg");
