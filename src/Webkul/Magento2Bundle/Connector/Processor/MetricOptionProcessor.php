<?php

namespace Webkul\Magento2Bundle\Connector\Processor;

use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Product processor to process and normalize entities to the standard format, normailze product and add variantAttribute to it
 *
 * This processor doesn't fetch media to directory nor does filter attribute & doesn't use the channel in configuration field but from job configuration
 *
 * @author    ankit yadav <ankit.yadav726@webkul.com>
 * @copyright webkul (http://webkul.com)
 * @license   http://store.webkul.com/license.html
 */
class MetricOptionProcessor extends \AbstractProcessor
{
   
    /**
     * @inheritdoc
     */
    public function process($attribute)
    {
        if ($attribute) {
            $attributesStandard = $attribute;
        } else {
            $attributesStandard = null;
        }

        return $attributesStandard;
    }
}
