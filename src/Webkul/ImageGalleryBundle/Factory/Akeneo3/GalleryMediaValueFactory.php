<?php

namespace Webkul\ImageGalleryBundle\Factory\Akeneo3;

use Akeneo\Component\StorageUtils\Exception\InvalidPropertyTypeException;
use Akeneo\Pim\Enrichment\Component\Product\Model\ValueInterface;
use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();

/**
 * Factory that creates simple product values
 *
 */
class GalleryMediaValueFactory implements \ValueFactoryInterface
{
    /** @var string */
    protected $productValueClass;

    /** @var string */
    protected $supportedAttributeTypes;

    /**
     * @param string $productValueClass
     * @param string $supportedAttributeTypes
     */
    public function __construct($productValueClass, $supportedAttributeTypes)
    {
        $this->productValueClass = $productValueClass;

        $this->supportedAttributeTypes = $supportedAttributeTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function create(\AttributeInterface $attribute, $channelCode, $localeCode, $data, $ignoreUnknownData = false): ValueInterface
    {
        $this->checkData($attribute, $data);

        if (null !== $data) {
            $data = $this->convertData($attribute, $data);
        }

        $value = new $this->productValueClass($attribute, $channelCode, $localeCode, $data);

        return $value;
    }



    /**
     * {@inheritdoc}
     */
    public function supports($attributeType): bool
    {
        return $attributeType === $this->supportedAttributeTypes;
    }

    /**
     * @param AttributeInterface $attribute
     * @param mixed              $data
     *
     * @throws InvalidPropertyTypeException
     */
    protected function checkData(\AttributeInterface $attribute, $data)
    {
        if (null === $data) {
            return;
        }

        if (!is_array($data)) {
            throw InvalidPropertyTypeException::arrayExpected(
                $attribute->getCode(),
                static::class,
                $data
            );
        }
    }

    /**
     * @param AttributeInterface $attribute
     * @param mixed              $data
     *
     * @return mixed
     */
    protected function convertData(\AttributeInterface $attribute, $data)
    {
        if (is_string($data) && '' === trim($data)) {
            $data = null;
        }

        if (
            \AttributeTypes::BOOLEAN === $attribute->getType() &&
            (1 === $data || '1' === $data || 0 === $data || '0' === $data)
        ) {
            $data = boolval($data);
        }

        return $data;
    }
}
