<?php

namespace Webkul\Magento2Bundle\JobParameters;

use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * @inheritdoc
 */
class JobParameters extends \JobParameters implements \IteratorAggregate, \Countable
{
    /**
     * Set the job parameter. This should never be used for a connector.
     * This is only for internal usage.
     *
     * @internal
     */
    public function set(string $key, $value):void
    {
        $this->parameters[$key] = $value;
    }
}
