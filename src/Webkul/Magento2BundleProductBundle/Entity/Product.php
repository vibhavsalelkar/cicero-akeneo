<?php

namespace Webkul\Magento2BundleProductBundle\Entity;

$obj = new \Webkul\Magento2BundleProductBundle\Listener\AkeneoVersionsCompatibility();
$obj->checkVersionAndCreateClassAliases();
/**
 * Product
 */
class Product extends \ModelProduct
{
    /**
     * @var native_json
     */
    private $bundleOptions;

    /**
     * Set bundleOptions
     *
     * @param native_json $bundleOptions
     *
     * @return Product
     */
    public function setBundleOptions($bundleOptions)
    {
        $this->bundleOptions = $bundleOptions;

        return $this;
    }

    /**
     * Get bundleOptions
     *
     * @return native_json
     */
    public function getBundleOptions()
    {
        return $this->bundleOptions;
    }

}
