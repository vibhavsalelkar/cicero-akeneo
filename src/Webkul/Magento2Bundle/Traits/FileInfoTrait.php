<?php

namespace Webkul\Magento2Bundle\Traits;

/**
* trait used to guess image extension basedOn image content without any third part extension
* @see https://stackoverflow.com/questions/6061505/detecting-image-type-from-base64-string-in-php
*/
trait FileInfoTrait
{
    public function getBytesFromHexString($hexdata)
    {
        for ($count = 0; $count < strlen($hexdata); $count+=2) {
            $bytes[] = chr(hexdec(substr($hexdata, $count, 2)));
        }

        return implode($bytes);
    }

    protected function getImageMimeType($imagedata)
    {
        $imagemimetypes = array(
            "jpeg" => "FFD8",
            "png" => "89504E470D0A1A0A",
            "gif" => "474946",
            "bmp" => "424D",
            "tiff" => "4949",
            "tiff" => "4D4D"
        );

        foreach ($imagemimetypes as $mime => $hexbytes) {
            $bytes = $this->getBytesFromHexString($hexbytes);
            if (substr($imagedata, 0, strlen($bytes)) == $bytes) {
                return $mime;
            }
        }

        return null;
    }
}
