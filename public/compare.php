<?php
// init the image objects
$image1 = new imagick();
$image2 = new imagick();

// set the fuzz factor (must be done BEFORE reading in the images)
$image1->SetOption('fuzz', '2%');

$img1 = @fopen('https://img.youtube.com/vi/Ri3WsPDi4MY/1.jpg', 'rb');
$img2 = @fopen('https://img.youtube.com/vi/Ri3WsPDi4MY/2.jpg', 'rb');
if (!$handle) {
    echo "Error: " . $http_response_header[0];
} else {
// read in the images
    $image1->readImageFile($handle);
    $image2->readImage("1.jpg");

// compare the images using METRIC=1 (Absolute Error)
    $result = $image1->compareImages($image2, 1);

// print out the result
    echo "The image comparison 2% Fuzz factor is: " . $result[1];
}