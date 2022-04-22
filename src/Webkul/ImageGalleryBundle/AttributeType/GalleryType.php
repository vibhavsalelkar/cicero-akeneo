<?php

namespace Webkul\ImageGalleryBundle\AttributeType;

/**
 * Table attribute type
 *
 */
class GalleryType extends \AbstractAttributeType
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return GalleryAttributeTypes::WEBKUL_GALLERY;
    }
}
