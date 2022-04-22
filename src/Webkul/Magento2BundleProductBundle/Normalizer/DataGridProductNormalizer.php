<?php
namespace Webkul\Magento2BundleProductBundle\Normalizer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Webkul\Magento2Bundle\Services\Magento2Connector;

// use Pim\Bundle\DataGridBundle\Normalizer\ProductNormalizer as BaseClass;

class DataGridProductNormalizer extends \BaseDatagridProductNormalizer 
{

    /**
     * @param CollectionFilterInterface $filter
     * @param ImageNormalizer           $imageNormalizer
     * @param Magento2Connector         $connectorService
     */
    public function __construct(
        \CollectionFilterInterface $filter,
        \ImageNormalizer $imageNormalizer,
        Magento2Connector $connectorService
    ) {
        parent::__construct($filter, $imageNormalizer);
        $this->connectorService = $connectorService;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($product, $format = null, array $context = [])
    {
       
       
        $data = parent::normalize($product, $format, $context);
        $data['complete_group_product'] = null;
        $data['complete_bundle_product'] = null;
        
        if(null != $product->getIdentifier()) {
            $mapping = $this->connectorService->getProductMapping($product->getIdentifier());
        } else {
            $mapping = null;
        }
        
        $associations = $product->getAssociations();
        foreach ($associations as $association) {
            $associationType = $association->getAssociationType();
            if($associationType->getCode() === 'webkul_magento2_groupped_product' && $mapping && $mapping->getType() === "grouped") {
                $data['complete_group_product'] = count($association->getProducts());
                
                break; 
            }
        }

        if($mapping && $mapping->getType() === "bundle" && method_exists($product, 'getBundleOptions')) {
            $count = 0;
            $bundleOptions = $product->getBundleOptions();
            if (is_array($bundleOptions)) {
                foreach ($bundleOptions as $value) {
                    if (is_array($value) && isset($value['products']) && is_array($value['products'])) {
                        foreach ($value['products'] as $productIdentifier) {
                        if (!empty($productIdentifier)) {
                            $count++;
                        }
                        }
                    }
                }
            }
            $data['complete_bundle_product'] = $count;
        }
        return $data;
    }
}
