<?php

namespace Webkul\Magento2BundleProductBundle\Component\Catalog\Normalizer\Standard\Product;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

class PropertiesNormalizer extends \BasePropertiesNormalizer
{
    const FIELD_BUNDLE_OPTIONS = 'bundleOptions';
    
    protected $serializer;
    protected $normalizer;
    /**
     * @param \CollectionFilterInterface $filter The collection filter
     * @param NormalizerInterface $normalizer
     */

    public function __construct(\CollectionFilterInterface $filter, NormalizerInterface $normalizer = null)
    {
        if($normalizer) {
            parent::__construct($filter, $normalizer);
            $this->normalizer = $normalizer;
            $this->serializer = $normalizer;
        } else {
            parent::__construct($filter);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function normalize($product, $format = null, array $context = [])
    {
        $data = parent::normalize($product, $format, $context);

        $data[self::FIELD_BUNDLE_OPTIONS] = $this->serializer->normalize($product->getBundleOptions(), $format);
        
        return $data;
    }
}
