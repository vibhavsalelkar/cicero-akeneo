<?php

namespace Webkul\Magento2BundleProductBundle\Catalog\Updater\Setter;

class BundleOptionsFieldSetter extends \AbstractFieldSetter
{
    /**
     * @param array $supportedFields
     */
    public function __construct(array $supportedFields)
    {
        $this->supportedFields = $supportedFields;
    }

    
    /**
     * {@inheritdoc}
     *
     * Expected data input format : true|false
     */
    public function setFieldData($product, $field, $data, array $options = [])
    {
        if (!$product instanceof \ProductInterface) {
            throw \InvalidObjectException::objectExpected($product, \ProductInterface::class);
        }
        
        if (!is_array($data)) {
            throw \InvalidPropertyTypeException::arrayExpected(
                $field,
                static::class,
                $data
            );
        }

        $product->setBundleOptions($data);
    }
}
