<?php

namespace Webkul\Magento2Bundle\Component\FilePathGenerator;

interface MediaFileInterface
{
    /**
     * Generate the file short file path based in the key
     *
     * @param string $fileKey
     *
     * @return string
     */
    public function generateShortFilePath(string $fileKey): string;

    /**
     * check the file key exist in the file info repository
     *
     * @param string
     *
     * @return bool
     */
    public function findFileIdByfileKey(string $fileKey): ?\Akeneo\Tool\Component\FileStorage\Model\FileInfo;
}
