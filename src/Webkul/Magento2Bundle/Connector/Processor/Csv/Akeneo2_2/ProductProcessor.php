<?php

namespace Webkul\Magento2Bundle\Connector\Processor\Csv\Akeneo2_2;

use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

class ProductProcessor extends \PimProductProcessor
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
