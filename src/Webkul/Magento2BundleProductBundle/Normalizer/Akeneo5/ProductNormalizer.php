<?php

namespace Webkul\Magento2BundleProductBundle\Normalizer\Akeneo5;

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

    const FIELD_ASSOCIATIONS = 'associations';
    const FIELD_QUANTIFIED_ASSOCIATIONS = 'quantified_associations';

    /** @var NormalizerInterface */
    private $propertiesNormalizer;

    /** @var NormalizerInterface */
    private $associationsNormalizer;

    /** @var NormalizerInterface */
    private $quantifiedAssociationsNormalizer;

    /**
     * ProductNormalizer constructor.
     *
     * @param NormalizerInterface $propertiesNormalizer
     * @param NormalizerInterface $associationsNormalizer
     * @param NormalizerInterface $quantifiedAssociationsNormalizer
     */
    public function __construct(
        NormalizerInterface $propertiesNormalizer,
        NormalizerInterface $associationsNormalizer,
        NormalizerInterface $quantifiedAssociationsNormalizer
    ) {
        $this->propertiesNormalizer = $propertiesNormalizer;
        $this->associationsNormalizer = $associationsNormalizer;
        $this->quantifiedAssociationsNormalizer = $quantifiedAssociationsNormalizer;
    }


    public function normalize($product, $format = null, array $context = [])
    {
        $normalizedProduct = parent::normalize($product, $format, $context);
        $normalizedProduct[self::FIELD_ASSOCIATIONS] = $this->associationsNormalizer->normalize($product, $format, $context);
        $normalizedProduct[self::FIELD_QUANTIFIED_ASSOCIATIONS] = $this->quantifiedAssociationsNormalizer->normalize($product, $format, $context);
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
