<?php

namespace Webkul\Magento2GroupProductBundle\Normalizer;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webkul\Magento2Bundle\Services\Magento2Connector;

/**
 * Product normalizer
 *
 */
class ProductNormalizer extends \BaseProductNormalizer implements NormalizerInterface
{

    /** @var Magento2Connector */
    private $connectorService;


    /**
     * @param NormalizerInterface                       $normalizer
     * @param NormalizerInterface                       $versionNormalizer
     * @param \VersionManager                            $versionManager
     * @param \ImageNormalizer                           $imageNormalizer
     * @param \LocaleRepositoryInterface                 $localeRepository
     * @param \StructureVersionProviderInterface         $structureVersionProvider
     * @param \FormProviderInterface                     $formProvider
     * @param \AttributeConverterInterface               $localizedConverter
     * @param \ConverterInterface                        $productValueConverter
     * @param ObjectManager                             $productManager
     * @param \CompletenessManager                       $completenessManager
     * @param \ChannelRepositoryInterface                $channelRepository
     * @param \CollectionFilterInterface                 $collectionFilter
     * @param NormalizerInterface                       $completenessCollectionNormalizer
     * @param \UserContext                               $userContext
     * @param \CompletenessCalculatorInterface           $completenessCalculator
     * @param \EntityWithFamilyValuesFillerInterface     $productValuesFiller
     * @param \EntityWithFamilyVariantAttributesProvider $attributesProvider
     * @param \VariantNavigationNormalizer               $navigationNormalizer
     * @param \AscendantCategoriesInterface|null         $ascendantCategoriesQuery
     * @param NormalizerInterface                       $incompleteValuesNormalizer
     * @param \MissingAssociationAdder                   $missingAssociationAdder
     * @param NormalizerInterface                       $parentAssociationsNormalizer
     * @param Magento2Connector                         $connectorService
     */
    public function __construct(
        NormalizerInterface $normalizer,
        NormalizerInterface $versionNormalizer,
        \VersionManager $versionManager,
        \ImageNormalizer $imageNormalizer,
        \LocaleRepositoryInterface $localeRepository,
        \StructureVersionProviderInterface $structureVersionProvider,
        \FormProviderInterface $formProvider,
        \AttributeConverterInterface $localizedConverter,
        \ConverterInterface $productValueConverter,
        ObjectManager $productManager,
        \CompletenessManager $completenessManager,
        \ChannelRepositoryInterface $channelRepository,
        \CollectionFilterInterface $collectionFilter,
        NormalizerInterface $completenessCollectionNormalizer,
        \UserContext $userContext,
        \CompletenessCalculatorInterface $completenessCalculator,
        \EntityWithFamilyValuesFillerInterface $productValuesFiller,
        \EntityWithFamilyVariantAttributesProvider $attributesProvider,
        \VariantNavigationNormalizer $navigationNormalizer,
        \AscendantCategoriesInterface $ascendantCategoriesQuery,
        NormalizerInterface $incompleteValuesNormalizer,
        \MissingAssociationAdder $missingAssociationAdder,
        NormalizerInterface $parentAssociationsNormalizer,
        Magento2Connector $connectorService
    ) {
        parent::__construct(
            $normalizer,
            $versionNormalizer,
            $versionManager,
            $imageNormalizer,
            $localeRepository,
            $structureVersionProvider,
            $formProvider,
            $localizedConverter,
            $productValueConverter,
            $productManager,
            $completenessManager,
            $channelRepository,
            $collectionFilter,
            $completenessCollectionNormalizer,
            $userContext,
            $completenessCalculator,
            $productValuesFiller,
            $attributesProvider,
            $navigationNormalizer,
            $ascendantCategoriesQuery,
            $incompleteValuesNormalizer,
            $parentAssociationsNormalizer,
            $missingAssociationAdder
        );
        $this->connectorService = $connectorService;
    }

    /**
     * {@inheritdoc}
     *
     * @param \ProductInterface $product
     */
    public function normalize($product, $format = null, array $context = [])
    {
        $normalizedProduct = parent::normalize($product, $format, $context);

        if (isset($normalizedProduct['identifier'])) {
            $mapping = $this->connectorService->getProductMapping($normalizedProduct['identifier']);
        } else {
            $mapping = null;
        }
        
        if (!empty($normalizedProduct['associations']['webkul_magento2_groupped_product']['products']) || ($mapping && $mapping->getType() === 'grouped')) {
            $normalizedProduct['meta']['form'] = 'pim-product-group-edit-form';
        }
        if (($mapping && $mapping->getType() === 'bundle')) {
            $normalizedProduct['meta']['form'] = 'pim-product-bundle-edit-form';
        }

        return $normalizedProduct;
    }
}
