<?php

namespace Webkul\Magento2Bundle\Services;

class TemporaryDataManager
{
    const FILE_NAME = 'product_models_data';

    public function addRow(array $data, $directory)
    {
        $file = $directory . SELF::FILE_NAME;
        $fp = fopen($file, "a+");

        fputcsv(
            $fp, // The file pointer
            $data, // The fields
            '|', // The delimiter
            '"'
        );
        fclose($fp);
    }

    public function getFileName($directory)
    {
        $file = $directory . SELF::FILE_NAME;
        
        return $file;
    }

    public function removeFile($fileName)
    {
        if (strpos($fileName, SELF::FILE_NAME)) {
            unlink($fileName);
        }
    }
}
