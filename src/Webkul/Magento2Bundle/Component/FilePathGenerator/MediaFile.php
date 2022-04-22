<?php

namespace Webkul\Magento2Bundle\Component\FilePathGenerator;

class MediaFile implements MediaFileInterface
{
    /** @var \FileInfoRepositoryInterface */
    protected $fileInfoRepository;

    /**
     * @params \FileInfoRepositoryInterface $fileInfoRepository
     */
    public function __construct(\FileInfoRepositoryInterface $fileInfoRepository)
    {
        $this->fileInfoRepository = $fileInfoRepository;
    }

    /**
     * @inheritdoc
     */
    public function generateShortFilePath(string $fileKey) :string
    {
        $fileInfo = $this->findFileIdByfileKey($fileKey);
        
        if (! $fileInfo) {
            return '';
        }

        return $fileInfo->getId() . '/' . $fileInfo->getOriginalFilename();
    }

    public function getFullPathByShortFileName($filename)
    {
        $fileNameInfoArray = explode('/', $filename);
        if (2 !== count($fileNameInfoArray)) {
            return '';
        }

        $fileId = $fileNameInfoArray[0];

        $fileOriginalName = $fileNameInfoArray[1];

        $fileInfo = $this->findFileIdByfileId($fileId);

        if ($fileInfo) {
            return $fileInfo->getKey();
        }

        return '';
    }
    
    /**
     * @inheritdoc
     */
    public function findFileIdByfileKey(string $fileKey) : ?\Akeneo\Tool\Component\FileStorage\Model\FileInfo
    {
        return $this->fileInfoRepository->findOneBy([
            'key' => $fileKey,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function findFileIdByfileId(string $fileId) : ?\Akeneo\Tool\Component\FileStorage\Model\FileInfo
    {
        return $this->fileInfoRepository->findOneBy([
            'id' => $fileId,
        ]);
    }
}
