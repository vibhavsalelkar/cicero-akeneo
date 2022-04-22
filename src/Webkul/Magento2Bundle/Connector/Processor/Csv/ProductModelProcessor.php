<?php

namespace Webkul\Magento2Bundle\Connector\Processor\Csv;

use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

class ProductModelProcessor extends \PimProductProcessor
{
    /**
     * Fetch medias on the local filesystem
     *
     * @param \EntityWithFamilyInterface $product
     * @param string           $directory
     */
    protected function fetchMedia(\EntityWithFamilyInterface $product, $directory)
    {
        //
    }
}
