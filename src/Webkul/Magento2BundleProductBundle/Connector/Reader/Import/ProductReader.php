<?php

namespace Webkul\Magento2BundleProductBundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Connector\Reader\Import\ProductReader as BaseReader;
use Webkul\Magento2BundleProductBundle\Repository\JobDataMappingRepository;
use Webkul\Magento2BundleProductBundle\Traits\JobDataMappingTrait;

/**
 * Product Reader Class reads the bundle products from the Magento2
 */
class ProductReader extends BaseReader
{
    use JobDataMappingTrait;
    
    
    const MAPPING_TYPE = 'associatedLinks';

    protected $magentoProductTypes = ['simple', 'virtual', 'bundle'];

    /** @var string $magentoProductType */
    protected $magentoProductType = 'bundle';
    

    /** @var JobDataMappingRepository $jobDataMappingRepository */
    private $jobDataMappingRepository;

    /** 
     * {@inheritdoc} 
     */
    public function initialize()
    {
        $this->jobDataMappingRepository = $this->em->getRepository('Magento2BundleProductBundle:JobDataMapping');
        parent:: initialize();
    }

    /** 
     * {@inheritdoc} 
     */
    protected function getFormatedProductBySKU($productSKU, $parentCode = NULL) 
    {
        $product = $this->getMagentoProduct($productSKU, $this->storeCode);
        $formatedProduct = $this->getFormatedProduct($product);

        if (!empty($product['extension_attributes']['bundle_product_options'])) {

            if($product['custom_attributes']) {
                $formatedProduct['bundleOptions'] = array_column(array_filter(array_map(function($arr) { 
                        if(isset($arr['attribute_code']) && in_array($arr['attribute_code'], ['price_view', 'shipment_type', 'sku_type', 'weight_type', 'price_type'])) {
                            switch($arr['attribute_code']) {
                                case 'shipment_type':
                                    $arr = [ 'attribute_code' => $arr['attribute_code'], 
                                            'value' => empty($arr['value']) ? "together" : "separately"
                                    ];    

                                    break;
                                case 'price_view':
                                    $arr = [ 'attribute_code' => 'bundle_' . $arr['attribute_code'],
                                            'value' => empty($arr['value']) ? "Price range" : "As low as"
                                    ];

                                    break;
                                default:
                                    $arr = [ 'attribute_code' => 'bundle_' . $arr['attribute_code'],
                                            'value' => empty($arr['value']) ? true : false
                                    ];
                            }   

                            return $arr;
                        }
                    }, 
                    $product['custom_attributes']
                )), 'value', 'attribute_code');
            }

            $options = array_map(function($option) {
                    $option['products'] = $option['product_links'];
                    unset($option['product_links']);

                    return $option;
                },
                $product['extension_attributes']['bundle_product_options']
            );
            
            $formatedProduct['bundleOptions'] = array_merge($formatedProduct['bundleOptions'], $options);
            $associatedProducts = [];

            foreach($options as $option) {
                $associatedProducts = array_merge($associatedProducts, 
                isset($option['products']) && is_array($option['products']) ? array_column($option['products'], 'sku') : []);
            }

            $this->addJobDataMapping($productSKU, self::MAPPING_TYPE, $associatedProducts);
        }

        return $formatedProduct;
    }
  
}
