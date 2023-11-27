<?php

namespace Webkul\MultiVendor\Services;

abstract class GlobalService
{
    const ALLOWED_IMAGE_SIZE = [
        "minWidth" => '200',
        "maxWidth" => '400',
        "minHeight" => '200',
        "maxHeight" => '400',
    ];

    const ALLOWED_IMAGE_FORMAT = [
        'image/png',
        'image/jpg',
        'image/jpeg',
    ];

    public function verifyImage($file)
    {
        
        $verifiedImageSize = $this->verifyImageSize($file->getSize());
        $verifiedImageFormat = $this->verifyImageFormat($file->getMimeType());

        return ($verifiedImageSize && $verifiedImageFormat);
    }

    public function verifyImageSize($fileSize)
    {
        if ($fileSize > 1000000) {
            return false;
        }
        return true;
    }

    public function verifyImageFormat($fileMimeType)
    {
        return (array_search($fileMimeType, self::ALLOWED_IMAGE_FORMAT) !== false);
    }
}
