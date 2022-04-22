<?php

namespace Webkul\ImageGalleryBundle\Normalizer\Standard;

use Webkul\ImageGalleryBundle\Model\GalleryMediaValue;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();

class GalleryNormalizer implements NormalizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function normalize($asset, $format = null, array $context = [])
    {
        $media = [];
        $storedRelatedTag = [];
        foreach ($asset->getMedias()->getValues() as $values) {
            $media[] = [
                            "original_file_name" => $values->getOriginalFilename(),
                            "filePath" => $values->getFilePath()
                        ];
        }
        foreach ($asset->getTags()->getValues() as $tags) {
            $storedRelatedTag[] =$tags->getCode();
        }
        return [
            'code'        => $asset->getCode(),
            'title'  => $asset->getTitle(),
            'description'   => $asset->getDescription(),
            'starred' => $asset->getStarred(),
            'created_at'     => !empty($asset->getUpdatedAt()) ? date('D M j G:i:s T Y', $asset->getCreatedAt()->getTimestamp()) : '',
            'updated_at'  =>  !empty($asset->getUpdatedAt()) ? date('D M j G:i:s T Y', $asset->getUpdatedAt()->getTimestamp()) : '',
            'alt'  => $asset->getAlt(),
            'media'  => $media,
            // 'is_checked' => false,
            'tags' => !empty($storedRelatedTag) ? implode(' & ', $storedRelatedTag) : null,
            'expiration_date' => $asset->getExpirationDate(),
            'group' => $asset->getGalleryGroup(),
            // 'labels'      => $this->normalizeLabels($asset, $context),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof GalleryMediaValue && 'standard' === $format;
    }

    /**
     * Returns an array containing the label values
     *
     * @param GalleryMediaValue $asset
     * @param array                    $context
     *
     * @return array
     */
    protected function normalizeLabels(GalleryMediaValue $asset, $context)
    {
        $locales = isset($context['locales']) ? $context['locales'] : [];
        $labels = array_fill_keys($locales, null);

        foreach ($asset->getColumnValues() as $translation) {
            if (empty($locales) || in_array($translation->getLocale(), $locales)) {
                $labels[$translation->getLocale()] = $translation->getValue();
            }
        }

        return $labels;
    }
}
