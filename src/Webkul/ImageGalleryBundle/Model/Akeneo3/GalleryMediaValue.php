<?php

namespace Webkul\ImageGalleryBundle\Model\Akeneo3;

use Pim\Component\Catalog\Model\AbstractValue;
use Pim\Component\Catalog\Model\AttributeInterface;
use Pim\Component\Catalog\Model\ValueInterface;


/**
 * Product value for Table atribute type
 *
 */
class GalleryMediaValue extends \AbstractValue implements \ValueInterface
{
    /** @var string[] */
    protected $data;

    /**
     * @param \AttributeInterface $attribute
     * @param string             $channel
     * @param string             $locale
     * @param mixed              $data
     */
    public function __construct(\AttributeInterface $attribute, $channel, $locale, $data)
    {
        if (method_exists($this, 'setAttribute') && method_exists($this, 'setScope') && method_exists($this, 'setLocale')) {
            $this->setAttribute($attribute);
            $this->setScope($channel);
            $this->setLocale($locale);
        } else {
            $this->attributeCode = $attribute->getCode();
            $this->scopeCode = $channel;
            $this->localeCode = $locale;
        }

        $this->data = $data;
    }

    /**
     * @return string[]
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param string $item
     */
    public function removeItem(string $item)
    {
        $data = array_filter($this->data, function ($value) use ($item) {
            return $value !== $item;
        });
        $this->data = array_values($data);
    }

    public function isEqual(\ValueInterface $value): bool
    {
        if (!$value instanceof GalleryMediaValue) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return implode(', ', $this->data);
    }
}
