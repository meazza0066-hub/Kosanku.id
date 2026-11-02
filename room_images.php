<?php
function room_image_path($roomNumber)
{
    $images = [
        'kamar1.webp',
        'kamar2.webp',
        'kamar3.webp',
        'kamar4.webp',
        'kamar5.webp',
        'kamar6.webp',
        'kamar7.webp',
        'kamar8.webp',
    ];
    $roomKey = strtolower(trim((string) $roomNumber));
    $imageIndex = hexdec(substr(sha1('room-photo-shuffle-458:' . $roomKey), 0, 8)) % count($images);

    return $images[$imageIndex];
}
