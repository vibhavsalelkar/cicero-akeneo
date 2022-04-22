<?php

namespace Webkul\ImageGalleryBundle\Updater\Setter;

use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();

/**
 * Sets a Media data in a product.
 *
 */
class MediaGroupSetter extends \AbstractAttributeSetter
{
    /**
     * @param EntityWithValuesBuilderInterface   $entityWithValuesBuilder
     * @param string[]                           $supportedTypes
     */
    public function __construct(
        \EntityWithValuesBuilderInterface $entityWithValuesBuilder,
        array $supportedTypes
    ) {
        parent::__construct($entityWithValuesBuilder);

        $this->supportedTypes = $supportedTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttributeData(
        \EntityWithValuesInterface $entityWithValues,
        \AttributeInterface $attribute,
        $data,
        array $options = []
    ) {
        $options = $this->resolver->resolve($options);

        $this->entityWithValuesBuilder->addOrReplaceValue(
            $entityWithValues,
            $attribute,
            $options['locale'],
            $options['scope'],
            $data
        );
    }
}
