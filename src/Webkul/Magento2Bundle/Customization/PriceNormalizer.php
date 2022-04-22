<?php

namespace Webkul\Magento2Bundle\Customization;

use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * @Override the Baseprice normalizer to change the deciaml precision
 */
class PriceNormalizer extends \PimPriceNormalizer
{
    const DECIMAL_PRECISION = 4;
}
