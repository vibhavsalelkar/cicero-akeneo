<?php

namespace Webkul\Magento2BundleProductBundle\Normalizer;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webkul\Magento2Bundle\Services\Magento2Connector;
/**
 * Product normalizer
 *
 */
class ProductNormalizer extends \BaseProductNormalizer implements NormalizerInterface
{

    /** @var Magento2Connector */
    private $connectorService; 

    public function normalize($product, $format = null, array $context = [])
    {
        $normalizedProduct = parent::normalize($product, $format, $context);
        $mapping = null;

        if(isset($normalizedProduct['identifier'])) {
            $mapping = $this->connectorService->getProductMapping($normalizedProduct['identifier']);
        }
        
        if(!empty($normalizedProduct['associations']['webkul_magento2_groupped_product']['products']) || ($mapping && $mapping->getType() === 'grouped')) {
            $normalizedProduct['meta']['form'] = 'pim-product-group-edit-form';
        }
        
        if(($mapping && $mapping->getType() === 'bundle')) {
            $normalizedProduct['meta']['form'] = 'pim-product-bundle-edit-form';
        }
        
        return $normalizedProduct;
    }

}
