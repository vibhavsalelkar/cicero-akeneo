<?php

declare(strict_types=1);

namespace Webkul\ImageGalleryBundle\Factory\Akeneo4;

use Akeneo\Pim\Enrichment\Component\Product\Model\ValueInterface;
use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Pim\Structure\Component\Query\PublicApi\AttributeType\Attribute;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException;
use Akeneo\Pim\Enrichment\Component\Product\Factory\Value\ScalarValueFactory;
use Akeneo\Pim\Enrichment\Component\Product\Factory\Value\ValueFactory;
use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();

final class GalleryMediaValueFactory extends ScalarValueFactory implements ValueFactory
{
    public function createWithoutCheckingData(Attribute $attribute, ?string $channelCode, ?string $localeCode, $data): \ValueInterface
    {
        return parent::createWithoutCheckingData($attribute, $channelCode, $localeCode, $data);
    }

    public function createByCheckingData(Attribute $attribute, ?string $channelCode, ?string $localeCode, $data): \ValueInterface
    {
        // if (!is_bool($data)) {
        //     throw InvalidPropertyTypeException::booleanExpected(
        //         $attribute->code(),
        //         static::class,
        //         $data
        //     );
        // }

        return $this->createWithoutCheckingData($attribute, $channelCode, $localeCode, $data);
    }

    public function supportedAttributeType(): string
    {
        return 'pim_catalog_gallery_group';
    }
}
