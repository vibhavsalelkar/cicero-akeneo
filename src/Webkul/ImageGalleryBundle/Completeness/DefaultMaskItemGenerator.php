<?php

declare(strict_types=1);

namespace Webkul\ImageGalleryBundle\Completeness;

use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Pim\Enrichment\Component\Product\Completeness\MaskItemGenerator\MaskItemGeneratorForAttributeType;

class DefaultMaskItemGenerator implements MaskItemGeneratorForAttributeType
{
    public function forRawValue(string $attributeCode, string $channelCode, string $localeCode, $value): array
    {
        return [
            sprintf(
                '%s-%s-%s',
                $attributeCode,
                $channelCode,
                $localeCode
            )
        ];
    }

    public function supportedAttributeTypes(): array
    {
        return [
            'pim_catalog_gallery_group'
        ];
    }
}