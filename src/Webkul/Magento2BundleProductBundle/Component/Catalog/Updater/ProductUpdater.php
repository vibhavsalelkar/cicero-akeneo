<?php

declare(strict_types=1);
namespace Webkul\Magento2BundleProductBundle\Component\Catalog\Updater;

use Doctrine\Common\Util\ClassUtils;

class ProductUpdater extends \BaseProductUpdater
{
    /**
     * @param \ProductInterface $product
     * @param                  $field
     * @param                  $data
     * @param array            $options
     */
    protected function setData(\ProductInterface $product, $field, $data, array $options = []): void
    {
        switch ($field) {
            case 'bundleOptions':                
                if($data != null) {
                    $this->updateProductFields($product, $field, $data);
                }
                break;
            
            default:
                parent::setData($product, $field, $data, $options);
        }
    }
}