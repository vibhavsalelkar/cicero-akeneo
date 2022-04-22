<?php
namespace Webkul\Magento2GroupProductBundle\Normalizer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Webkul\Magento2GroupProductBundle\Services\Magento2GroupProductConnector;
use Oro\Bundle\PimDataGridBundle\Normalizer\ProductAndProductModelRowNormalizer as BaseClass;

class RowDataGridProductNormalizer extends BaseClass
{

    /**
     * @param CollectionFilterInterface $filter
     * @param \ImageNormalizer           $imageNormalizer
     * @param ConnectorService         $connectorService
     */
    public function __construct(
        \ImageNormalizer $imageNormalizer,
        Magento2GroupProductConnector $connectorService
    ) {
        parent::__construct($imageNormalizer);
        $this->connectorService = $connectorService;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($product, $format = null, array $context = [])
    {
        $mapping = null;
        $data = parent::normalize($product, $format, $context);
        $data['complete_group_product'] = null;
        $data['complete_bundle_product'] = null;
        
        if (null != $product->identifier()) {
            $mapping = $this->connectorService->getProductMapping($product->identifier());
        }
        if ($mapping && $mapping->getType() === "grouped") {
            $data['complete_group_product'] = $this->connectorService->getCountGroupBundleProducts($product->identifier(), $mapping->getType());
        }
        if ($mapping && $mapping->getType() === "bundle") {
            $data['complete_bundle_product'] = $this->connectorService->getCountGroupBundleProducts($product->identifier(), $mapping->getType());
        }

        return $data;
    }
}
