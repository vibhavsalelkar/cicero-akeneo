<?php

namespace Webkul\Magento2BundleProductBundle\Extension\Filter;

class FilterExtension extends \BaseExtension
{
    /**
     * @inheritdoc
     */
    protected function getCategoryFilterConfig($gridName)
    {
        $gridConfigs = [
            'wk-bundle-product-association-product-picker-grid' => [
                'type'      => 'product_category',
                'data_name' => 'category'
            ]
        ];

        if (isset($gridConfigs[$gridName])) {
            $filterConfig = $gridConfigs[$gridName];
        } else {
            $filterConfig = parent::getCategoryFilterConfig($gridName);
        }

        return $filterConfig; 
    }
}
