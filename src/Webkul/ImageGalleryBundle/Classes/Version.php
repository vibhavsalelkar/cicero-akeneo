<?php
namespace Webkul\ImageGalleryBundle\Classes;

/**
 * this class return the latest current commit version.
 */
class Version
{
    const CURRENT_VERSION = '2.0.0';

    public function getModuleVersion()
    {
        return self::CURRENT_VERSION;
    }
}
