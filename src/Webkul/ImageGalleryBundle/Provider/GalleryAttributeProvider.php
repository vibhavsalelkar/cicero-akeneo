<?php

namespace Webkul\ImageGalleryBundle\Provider;

use Webkul\ImageGalleryBundle\AttributeType\GalleryAttributeTypes;

class GalleryAttributeProvider implements \FieldProviderInterface
{

    /**
     * {@inheritdoc}
     */
    public function getField($element)
    {
        return GalleryAttributeTypes::WEBKUL_GALLERY;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($element)
    {
        return $element instanceof \AttributeInterface
            && GalleryAttributeTypes::WEBKUL_GALLERY === $element->getType();
    }
}
