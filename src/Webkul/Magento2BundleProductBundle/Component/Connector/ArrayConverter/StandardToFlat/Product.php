<?php

namespace Webkul\Magento2BundleProductBundle\Component\Connector\ArrayConverter\StandardToFlat;
$obj = new \Webkul\Magento2BundleProductBundle\Listener\AkeneoVersionsCompatibility();
$obj->checkVersionAndCreateClassAliases();

class Product extends \StandardToFlatProduct
{
    /**
     * {@inheritdoc}
     */
    protected function convertProperty($property, $data, array $convertedItem, array $options)
    {
       
        switch ($property) {
            case 'bundleOptions':
                $convertedItem = $this->convertBundleOptions($data, $convertedItem);
                break;
            default:
                $convertedItem = parent::convertProperty($property, $data, $convertedItem, $options);
        }
        return $convertedItem;
    }

    /**
     * Convert standard bundleOptions to flat bundle_values.
     *
     * @param mixed $data
     * @param array $convertedItem
     *
     * @return array
     */
    protected function convertBundleOptions($data, array $convertedItem)
    {
       
        $bundleValues = [];
        if (!array_key_exists('bundle_values', $convertedItem)) {
            $convertedItem['bundle_values'] = '';
        }
        if(!empty($data)) {
            if(isset($data['shipment_type'])) {
                
                $convertedItem['bundle_shipment_type'] = $data['shipment_type'];
                $convertedItem['product_type'] = "bundle";
                $convertedItem['bundle_price_type'] = !empty($data['bundle_price_type']) ? "dynamic" : "fixed";
                $convertedItem['bundle_sku_type'] = !empty($data['bundle_sku_type']) ? "dynamic" : "fixed"; 
                $convertedItem['bundle_weight_type'] = !empty($data['bundle_weight_type']) ? "dynamic" : "fixed";
                $convertedItem['bundle_price_view'] = !empty($data['bundle_price_view']) ?  $data['bundle_price_view'] : "Price range";

                unset($data['shipment_type']);
                unset($data['bundle_price_type']);
                unset($data['bundle_sku_type']);
                unset($data['bundle_weight_type']);
                unset($data['bundle_price_view']);
                
                foreach($data as $option) {
                    
                    if(isset($option['title']) && isset($option['type']) && isset($option['products'])) {
                        foreach($option['products'] as $product) {

                            $bundleValues [] = [
                                'name' => $option['title'],
                                'type' => $option['type'],
                                'required' => isset($option['required']) ? (int)$option['required'] : 0,
                                'sku' => $product['sku'],
                                'price' => $product['price'] ?? 0,
                                'default' => $product['is_default'] ?? 0,
                                'default_qty' => $product['qty'] ?? 0,
                                'price_type' => 'fixed',
                                'can_change_qty' => (isset($product['can_change_quantity']) && $product['can_change_quantity']) ? 1 : 0,
                            ]; 
                        }
                        
                    }
                }

                if(!empty($bundleValues)) {
                    $bundleValuesString = array_map(function($bundleValue) {
                            return implode(',', array_map(function($value, $key) {
                                if(is_array($value)) {
                                    return $key .'[]=' . implode('&'.$key.'[]', $value);
                                } else {
                                    return $key . '=' . $value;
                                }
                            }, $bundleValue, array_keys($bundleValue) ));
                        }, $bundleValues);
                        
                    $bundleValuesString = implode('|', $bundleValuesString);
                    $convertedItem['bundle_values'] = $bundleValuesString;
                }
            }
        }
        
        return $convertedItem;
    }

}
