<?php

namespace Webkul\Magento2Bundle\Classes;

class EnterpriceMediaAttributeSupport
{
    private $assetRepository;
    
    protected $channelRepository;

    protected $localeRepository;

    protected $supportedImageMimeTypes = ['image/png','image/jpeg','image/gif','image/bmp','image/vnd.microsoft.icon','image/tiff','image/svg+xml','image/vnd.adobe.photoshop'];

    public function __construct(?\Akeneo\Asset\Bundle\Doctrine\ORM\Repository\AssetRepository $assetRepository, $channelRepository, $localeRepository)
    {
        $this->assetRepository = $assetRepository;
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
    }

    /**
     * @param string $code
     * @param string $channelCode
     * @param string $localeCode
     *
     * @return string
     */
    public function getFileName($code, $channelCode = 'ecommerce', $localeCode = null): string
    {
        $asset = $this->findProductAssetByCodeOr404($code);
        $filename = '';
        
        if (null !== $channel = $this->channelRepository->findOneByIdentifier($channelCode)) {
            // $channelCode = null;
            $locale = null;
            if (null !== $localeCode) {
                $locale = $this->localeRepository->findOneByIdentifier($localeCode);
            }

            if (null !== $file = $asset->getFileForContext($channel, $locale)) {
                if (in_array($file->getMimeType(), $this->supportedImageMimeTypes)) {
                    $filename = $file->getKey();
                }
            }
        }

        return $filename;
    }

    /**
     * Find an Asset by its code or return a 404 response
     *
     * @param string $code
     *
     * @throws Exception
     *
     * @return AssetInterface
     */
    protected function findProductAssetByCodeOr404($code)
    {
        $productAsset = $this->assetRepository->findOneByIdentifier($code);

        if (null === $productAsset) {
            throw new \Exception(
                sprintf('Product asset with code "%s" cannot be found.', $code)
            );
        }

        return $productAsset;
    }
}
