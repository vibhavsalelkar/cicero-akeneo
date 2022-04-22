<?php

namespace Webkul\Magento2Bundle\Component\Normalizer;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * Transform the properties of a product object (fields and product values)
 * to a standardized array
 */
class PropertiesNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;

    const FIELD_IDENTIFIER = 'identifier';
    const FIELD_FAMILY = 'family';
    const FIELD_PARENT = 'parent';
    const FIELD_PARENT_SKU = 'parent-sku';
    const FIELD_ENABLED = 'status';
    const FIELD_VALUES = 'values';
    const FIELD_CREATED = 'created_at';
    const FIELD_UPDATED = 'updated_at';
    const FIELD_VISIBILITY = 'visibility';
    const FIELD_GROUPS = 'groups';
    const FIELD_CATEGORIES = 'categories';
    const FIELD_AXIS = 'axes';
    const FIELD_VARIANTS = 'variants';
    const VARIANT_ATTRIBUTES = 'allVariantAttributes';
    const FIELD_META_DATA = 'metadata';
    const FIELD_MAGENTO_PRODUCT_TYPE = 'type_id';
    const ATTRIBUTE_AS_IMAGE = 'main-image';
    const CONFIGURABLE_TYPE = 'configurable';
    const SIMPLE_TYPE = 'simple';
    const VARIANT_TYPE = 'variant';
    const GROUPED_TYPE = 'grouped';
    const BUNDELED_TYPE = 'bundle';
    const DOWNLOADABLETYPE_TYPE = 'downloadable';
    const BUNDLE_PRODUCT_OPTIONS = 'bundle_product_options';
    const TIER_PRICING_OPTIONS = 'tier_pricing_options';
    const DOWNLOADABLE_OPTIONS = 'downloadable_product_links';
    const DOWNLOADABLE_SAMPLE_OPTIONS = 'downloadable_product_samples';
    const LINKS_PURCHASED_SEPARATELY = 'links_purchased_separately';
    const LINKS_TITLE = 'links_title';
    const SAMPLES_TITLE = 'samples_title';
    const VISIBILITY_ALL = 4;
    const VISIBILITY_CATALOG = 3;
    const VISIBILITY_SEARCH = 1;
    const VISIBILITY_NOT_INDIVIDUALLY = 1;
    /** Supoort for Bundle discount */
    const BUNDLE_DISCOUNT_ATTRIBUTE = 'json_type';

    /** @var \CollectionFilterInterface */
    private $filter;

    /**
     * @param \CollectionFilterInterface $filter The collection filter
     */
    public function __construct(\CollectionFilterInterface $filter, $connectorService)
    {
        $this->filter = $filter;
        $this->connectorService = $connectorService;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($product, $format = null, array $context = [])
    {
        if (!$this->serializer instanceof NormalizerInterface) {
            throw new \LogicException('Serializer must be a normalizer');
        }

        $context = array_merge(['filter_types' => ['pim.transform.product_value.structured']], $context);
        $data = [];

        $data[self::FIELD_META_DATA] = [];
        $data[self::FIELD_META_DATA][self::FIELD_FAMILY] = $product->getFamily() ? $product->getFamily()->getCode() : null;
        // $data[self::FIELD_META_DATA][self::ATTRIBUTE_AS_IMAGE] = $product->getFamily() ? ($product->getFamily()->getAttributeAsImage() ? $product->getFamily()->getAttributeAsImage()->getCode() : null) : null;
        $otherMappings = $this->connectorService->getOtherMappings();
        $data[self::FIELD_META_DATA][self::ATTRIBUTE_AS_IMAGE] = isset($otherMappings['base_image']) && $otherMappings['base_image'] ? $otherMappings['base_image'] : null;
        $data[self::FIELD_META_DATA][self::FIELD_CATEGORIES] = $product->getCategoryCodes();
        // $data[self::FIELD_GROUPS] = $product->getGroupCodes();
        if (!$product instanceof \ProductModelInterface) {
            $data[self::FIELD_META_DATA][self::FIELD_IDENTIFIER] = $product->getIdentifier();
            $data[self::FIELD_ENABLED] = (int) $product->isEnabled();
        }

        if ($this->isVariantProduct($product) && null !== $product->getParent()) {
            $data[self::FIELD_AXIS] = $this->connectorService->getVariantAxes($product->getIdentifier());
            // $this->getVariantAxes($product);
            $data[self::VARIANT_ATTRIBUTES] = $data[self::FIELD_PARENT][self::VARIANT_ATTRIBUTES] = $this->connectorService->getFamilyVariantAttributs($product->getIdentifier());
            // $this->getAllVariantAttributes($product);
            $data[self::FIELD_VARIANTS] = $this->connectorService->getFamilyVariantCode($product->getIdentifier());
            // $product->getFamilyVariant()->getCode();
            $parent = $this->getMainParent($product);
            
            if ($parent && $parent instanceof \ProductModelInterface) {
                $data[self::FIELD_PARENT] = $this->normalize($parent, $format, $context);
                $data[self::FIELD_PARENT]['sku'] = $parent->getCode();
            }
            $data[self::FIELD_VISIBILITY]           = self::VISIBILITY_NOT_INDIVIDUALLY;
            $data[self::FIELD_MAGENTO_PRODUCT_TYPE] = self::VARIANT_TYPE;
        } elseif ($product instanceof \ProductModelInterface) {
            $data[self::FIELD_ENABLED] = 1;
            $data[self::FIELD_VISIBILITY]           = self::VISIBILITY_ALL;
            $data[self::FIELD_MAGENTO_PRODUCT_TYPE] = self::CONFIGURABLE_TYPE;
        } else {
            $data[self::FIELD_VISIBILITY]           = self::VISIBILITY_ALL;
            $data[self::FIELD_META_DATA][self::FIELD_PARENT] = null;
            $data[self::FIELD_MAGENTO_PRODUCT_TYPE] = self::SIMPLE_TYPE;
        }
        
        /** Group Products Support Start */
        if ($product && method_exists($product, 'hasAssociationForTypeCode') && $product->hasAssociationForTypeCode('webkul_magento2_groupped_product')) {
            if ($association= $this->getAssociationForTypeCode($product, 'webkul_magento2_groupped_product')) {
               if($association->getProducts()->count() > 0){
                $data[self::FIELD_MAGENTO_PRODUCT_TYPE] = self::GROUPED_TYPE;
               }
            }
        }
        /** Group Products Support End */

        /** Bundle Products Support Start */
        if (method_exists($product, 'getBundleOptions') && !empty($product->getBundleOptions())) {
            $data[self::FIELD_MAGENTO_PRODUCT_TYPE] = self::BUNDELED_TYPE;
            $data[self::FIELD_META_DATA][self::BUNDLE_PRODUCT_OPTIONS] = $product->getBundleOptions();
        }
        /** Bundle Products Support End */

        /** Downlaodable Products Support Start */
        /** If a product is downloadable as well as bundle or grouped then we will export this product as a downloadable prooduct */
        if ($product->getValue('downloadable_type') && $product->getValue('downloadable_type')->getData()['downloadable']) {
            if (isset($data[self::FIELD_MAGENTO_PRODUCT_TYPE])) {
                unset($data[self::FIELD_MAGENTO_PRODUCT_TYPE]);
            }
            if (isset($data[self::FIELD_META_DATA][self::BUNDLE_PRODUCT_OPTIONS])) {
                unset($data[self::FIELD_META_DATA][self::BUNDLE_PRODUCT_OPTIONS]);
            }

            $data[self::FIELD_MAGENTO_PRODUCT_TYPE] = self::DOWNLOADABLETYPE_TYPE;
            $data[self::DOWNLOADABLE_OPTIONS] = $product->getValue('downloadable_type')->getData()['downloadableOptions'] ? $product->getValue('downloadable_type')->getData()['downloadableOptions'] : [];
            $data[self::DOWNLOADABLE_SAMPLE_OPTIONS] = isset($product->getValue('downloadable_type')->getData()['sampleOptions']) ? $product->getValue('downloadable_type')->getData()['sampleOptions'] : [];
            $data[self::LINKS_TITLE] = $product->getValue('downloadable_type')->getData()['links_title'] ? $product->getValue('downloadable_type')->getData()['links_title'] : '';
            $data[self::SAMPLES_TITLE] = $product->getValue('downloadable_type')->getData()['samples_title'] ? $product->getValue('downloadable_type')->getData()['samples_title'] : '';
            $data[self::LINKS_PURCHASED_SEPARATELY] = $product->getValue('downloadable_type')->getData()['purchaseLinks'] ? 1 : 0;
        }
        /** Downlaodable Products Support End */

        /**  Tier Pricing Support Start */
        if (method_exists($product, 'getTierPricingOptions') && !empty($product->getTierPricingOptions())) {
            $data[self::FIELD_META_DATA][self::TIER_PRICING_OPTIONS] = $product->getTierPricingOptions();
        }
        /**  Tier Pricing Support End */

        $normalizedProductValues = $this->normalizeValues($product->getValues(), $format, $context);

        $data[self::FIELD_UPDATED] = $this->serializer->normalize($product->getUpdated(), $format);
        $data[self::FIELD_VALUES] = $normalizedProductValues;

        if (isset($data[self::FIELD_VALUES][$data[self::FIELD_META_DATA][self::ATTRIBUTE_AS_IMAGE]])) {
            $attributeAsImageData  = $data[self::FIELD_VALUES][$data[self::FIELD_META_DATA][self::ATTRIBUTE_AS_IMAGE]];
            foreach ($attributeAsImageData as $imageData) {
                if (isset($imageData['data'])) {
                    $data[self::FIELD_META_DATA]['main_image_data'] = $imageData['data'];
                }
            }
        }
        
        return $data;
    }
    protected function getAssociationForTypeCode($product, $typeCode)
    {
       foreach ($product->getAssociations() as $association) {
       if ($association->getAssociationType()->getCode() === $typeCode) {
           return $association;
        }
      }

     return null;
   }

    protected function isVariantProduct($product)
    {
        $flag = false;
        if (method_exists($product, 'isVariant')) {
            $flag = $product->isVariant();
        } else {
            $flag = ($product instanceof \VariantProductInterface);
        }

        return $flag;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof \ProductInterface && 'standard' === $format;
    }


    protected function getDataIndex($array)
    {
        return $array['data'] ?? null;
    }

    protected function getMainParent($product)
    {
        $maxLoop = 10;
        $self = $product;

        while ($self->getParent() instanceof \ProductModelInterface && $maxLoop) {
            $self = $self->getParent();
            $maxLoop--;
        }
        
        return $self !== $product ? $self : null;
    }

    protected function getVariantAxes($product)
    {
        $result = [];
        $familyVariant = $product->getFamilyVariant();
        $code = $familyVariant->getCode();
        
        if ($code) {
            $varAttributeSets = $familyVariant->getVariantAttributeSets();
            foreach ($varAttributeSets as $attrSet) {
                $axises = $attrSet->getAxes();
                foreach ($axises as $axis) {
                    $result[] = $axis->getCode();
                }
            }
        }

        return $result;
    }

    protected function getAllVariantAttributes($product)
    {
        $result = [];
        $familyVariant = $product->getFamilyVariant();
        $code = $familyVariant->getCode();

        if (!$product instanceof \ProductModelInterface) {
            $identifier = $product->getIdentifier();
        } else {
            $identifier = $product->getCode();
        }
        
        if ($code) {
            $varAttributeSets = $familyVariant->getVariantAttributeSets();
        
            foreach ($varAttributeSets as $attrSet) {
                $attributes = $attrSet->getAttributes();
                foreach ($attributes as $attribute) {
                    $result[] = $attribute->getCode();
                }
            }
        }
        return $result;
    }

    /**
     * Normalize the values of the product
     * @param \ValueCollectionInterface $values
     * @param string                   $format
     * @param array                    $context
     *
     * @return ArrayCollection
     */
    private function normalizeValues(\ValueCollectionInterface $values, $format, array $context = [])
    {
        foreach ($context['filter_types'] as $filterType) {
            $values = $this->filter->filterCollection($values, $filterType, $context);
        }
        $data = $this->serializer->normalize($values, $format, $context);

        return $data;
    }
}
