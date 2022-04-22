<?php

namespace Webkul\Magento2Bundle\Component\Normalizer;

use Akeneo\Pim\Structure\Component\Normalizer\InternalApi\AttributeOptionNormalizer as PimAttributeOptionNormalizer;
/**
 * Transform the properties of a product object (fields and product values)
 * to a standardized array
 */
class AttributeOptionNormalizer extends PimAttributeOptionNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $normalizedValues = parent::normalize($object, $format, $context);

        if(method_exists($object,'getImage')) {
            $normalizedValues['image'] = $object->getImage();
        }

        return $normalizedValues;
    }
}