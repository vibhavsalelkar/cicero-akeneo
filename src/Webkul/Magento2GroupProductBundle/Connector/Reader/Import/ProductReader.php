<?php

namespace Webkul\Magento2GroupProductBundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Connector\Reader\Import\ProductReader as BaseReader;
use Webkul\Magento2GroupProductBundle\Repository\JobDataMappingRepository;
use Webkul\Magento2GroupProductBundle\Traits\JobDataMappingTrait;

/**
 * Product Reader Class reads the grouped products from the Magento2
 */
class ProductReader extends BaseReader
{
    use JobDataMappingTrait;
    
    const MAPPING_TYPE = 'associatedLinks';
    
    /** @var string $magentoProductType */
    protected $magentoProductType = 'grouped';
    

    /** @var JobDataMappingRepository $jobDataMappingRepository */
    private $jobDataMappingRepository;

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->jobDataMappingRepository = $this->em->getRepository('Magento2GroupProductBundle:JobDataMapping');
        parent:: initialize();
    }

    protected function getMagentoProduct($productSKU, $storeCode)
    {
        $product = parent::getMagentoProduct($productSKU, $storeCode);
        
        if (!empty($product['product_links'])) {
            $assciatedProductLinks = array_map(
                function ($arr) {
                    if (isset($arr['link_type']) && $arr['link_type'] === 'associated') {
                        return $arr['linked_product_sku'] ?? null;
                    }
                },
                is_array($product['product_links']) ? $product['product_links'] : []
            );
            $associatedProducts = array_filter($assciatedProductLinks);
            $this->addJobDataMapping($productSKU, self::MAPPING_TYPE, $associatedProducts);
        }

        return $product;
    }
}
