<?php
namespace Webkul\Magento2Bundle\Component;

/**
 * this class return the latest current commit version.
 */
class Version
{
    const CURRENT_VERSION = '2.0';

    public function getModuleVersion()
    {
        return self::CURRENT_VERSION;
    }
}
