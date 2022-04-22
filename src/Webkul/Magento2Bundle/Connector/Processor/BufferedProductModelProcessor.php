<?php

namespace Webkul\Magento2Bundle\Connector\Processor;

use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Product processor to process and normalize entities to the standard format
 *
 */
class BufferedProductModelProcessor extends \PimProductProcessor
{

    /**
     * {@inheritdoc}
     */
    public function process($product)
    {
        return $product;
    }
}
