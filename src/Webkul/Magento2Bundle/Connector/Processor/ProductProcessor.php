<?php

namespace Webkul\Magento2Bundle\Connector\Processor;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Webkul\Magento2Bundle\Traits\FileInfoTrait;
use Webkul\Magento2Bundle\Traits\StepExecutionTrait;
use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer as Prop;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$versionCompatibily = new AkeneoVersionsCompatibility();
$versionCompatibily->checkVersionAndCreateClassAliases();

/**
 * Product processor to process and normalize entities to the standard format
 *
 */
//
class ProductProcessor extends \PimProductProcessor implements \ItemProcessorInterface, \StepExecutionAwareInterface
{
    use FileInfoTrait;

    use StepExecutionTrait;
    
    /** @var NormalizerInterface */
    protected $normalizer;

    /** @var \ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var \AttributeRepositoryInterface */
    protected $attributeRepository;

    /** @var \ObjectDetacherInterface */
    protected $detacher;

    /** @var StepExecution */
    protected $stepExecution;

    /** @var \BulkMediaFetcher */
    protected $mediaFetcher;

    /** @var \EntityWithFamilyValuesFillerInterface */
    protected $productValuesFiller;

    /** @var array */
    protected $magentoBaseFields = ['sku', 'name', 'price', 'status', 'visibility', 'weight', 'configurable_product_status'];

    /** @var array */
    protected $magentoNoncustomValuesArray = ['categories', 'axes', 'variants', 'allVariantAttributes'];
    
    /** @var string */
    protected $channel;

    /** @var Router */
    protected $router;

    protected $connectorService;

    /** @var string */
    protected $locale;

    /** @var bool */
    protected $vendorReferenceSelect = null;

    /** @var array */
    protected $allImageRoles = [
        'base_image' => 'image',
        'small_image' => 'small_image',
        'thumbnail_image' => 'thumbnail',
    ];

    protected $shortPathGenerator;
    
    /**
     * @param NormalizerInterface                    $normalizer
     * @param \ChannelRepositoryInterface            $channelRepository
     * @param \AttributeRepositoryInterface          $attributeRepository
     * @param \ObjectDetacherInterface               $detacher
     * @param \BulkMediaFetcher                      $mediaFetcher
     * @param \EntityWithFamilyValuesFillerInterface $productValuesFiller
     */
    public function __construct(
        NormalizerInterface $normalizer,
        \ChannelRepositoryInterface $channelRepository,
        \AttributeRepositoryInterface $attributeRepository,
        \ObjectDetacherInterface $detacher,
        \BulkMediaFetcher $mediaFetcher,
        \EntityWithFamilyValuesFillerInterface $productValuesFiller,
        Router $router,
        $connectorService
    ) {
        $this->normalizer = $normalizer;
        $this->detacher = $detacher;
        $this->channelRepository = $channelRepository;
        $this->attributeRepository = $attributeRepository;
        $this->mediaFetcher = $mediaFetcher;
        $this->productValuesFiller = $productValuesFiller;
        $this->router = $router;
        $this->connectorService = $connectorService;

    }

    /**
     * {@inheritdoc}
     */
    public function process($product, $recursiveCall = false)
    {
        if (!$recursiveCall && $product instanceof \ProductModel) {
            /* skip excess ProductModel */
            return;
        }

        if ($this->vendorReferenceSelect === null) {
            $this->vendorReferenceSelect = $this->connectorService->getVendorReferenceSelectCode();
        }

        $parameters = $this->stepExecution->getJobParameters();
        $scopes = $this->getChannelScope($this->stepExecution);
        if ($product instanceof \ProductModelInterface && method_exists($this->productValuesFiller, 'fromStandardFormat')) {
            $productStandard = $this->productValuesFiller->fillMissingValues($productStandard);
        }

        if (!$recursiveCall && $product instanceof \EntityWithFamilyInterface) {
            if (method_exists($this->productValuesFiller, 'fillMissingValues')) {
                $this->productValuesFiller->fillMissingValues($product);
            }

            if (is_array($scopes)) {
                $channelsCode = [];
                $channelsLocales = [];
                foreach ($scopes as $scope) {
                    $channel         = $this->channelRepository->findOneByIdentifier($scope);
                    $channelsCode[]  = $channel->getCode();
                    $channelsLocales = array_merge($channelsLocales, $channel->getLocaleCodes());
                }
                $productStandard = $this->getStandardFormat($channelsCode, array_unique($channelsLocales), $parameters, $product);
            } else {
                $channel          = $this->channelRepository->findOneByIdentifier($scopes);
                $channelLocales = $channel->getLocaleCodes();
                $productStandard  = $this->getStandardFormat(
                    [$channel->getCode()],
                    $channelLocales,
                    $parameters,
                    $product
                );
            }

            if ($product instanceof \ProductModelInterface && method_exists($this->productValuesFiller, 'fromStandardFormat')) {
                $productStandard = $this->productValuesFiller->fromStandardFormat($productStandard);
            }
        } else {
            $productStandard = $product;
        }

        if (!$recursiveCall && !empty($productStandard['parent'])) {
            $parentProductStandard = $productStandard['parent'];
            if (isset($productStandard[Prop::FIELD_ENABLED])) {
                $parentProductStandard['status'] = $productStandard[Prop::FIELD_ENABLED];
            }

            /** Manage the Configurable product status with the attribute configurable_product_status */
            if ($parentProductStandard[Prop::FIELD_MAGENTO_PRODUCT_TYPE] === 'configurable') {
                if (isset($parentProductStandard['values']['configurable_product_status'])) {
                    $configurableProductStatus = reset($parentProductStandard['values']['configurable_product_status']);
                    $parentProductStandard['status'] = (int)$configurableProductStatus['data'] ?? $productStandard[Prop::FIELD_ENABLED];
                }
            }
            
            $productStandard['parent'] = $this->process($parentProductStandard, true);
        }
        $this->fillProductValues($productStandard);
        if ($this->areAttributesToFilter($parameters)) {
            $attributesToFilter = $this->getAttributesToFilter($parameters);
            $productStandard['values'] = $this->filterValues($productStandard['values'], $attributesToFilter);
        }

        if (isset($productStandard['associations'])) {
            unset($productStandard['associations']);
        }

        ///////////// custom code start //////////////
        $otherMappings = $this->connectorService->getOtherMappings();
        $customImageRoles = isset($otherMappings['custom_image_roles']) ? $otherMappings['custom_image_roles'] : [];
        foreach ($customImageRoles as $customImageRole => $customImageRoleLabel) {
            if (!isset($this->allImageRoles[$customImageRole])) {
                $this->allImageRoles[$customImageRole] = $customImageRole;
            }
        }
        
        $otherSettings = $this->connectorService->getSettings();
        /* images and videos */
        if ($parameters->has('with_media') && $parameters->get('with_media')) {
            $mediaAttributes = !empty($otherMappings['images']) ? $otherMappings['images'] : [];
            $mediaAltAttributes = !empty($otherMappings['images_alts']) ? $otherMappings['images_alts'] : [];
            $combineData = count($mediaAttributes) == count($mediaAltAttributes) ? array_combine($mediaAttributes, $mediaAltAttributes) : array_combine(array_slice($mediaAttributes, 0, count($mediaAltAttributes)), $mediaAltAttributes);
            $productStandard['media_gallery_entries'] = [];
            $position = 0;
            foreach ($mediaAttributes as $mediaAttribute) {
                if ($this->isSameLevelImage($mediaAttribute, $productStandard)) {
                    if (!empty($productStandard['values'][$mediaAttribute])) {
                        $childImageRoles = null;
                        $productImageRoles = null;
                        if (!empty($productStandard[Prop::FIELD_PARENT])) {
                            $imageRoles = $childImageRoles = $this->getImageRoles($mediaAttribute, $otherMappings, true);
                        } else {
                            $imageRoles = $productImageRoles = $this->getImageRoles($mediaAttribute, $otherMappings);
                        }
                        $altData = isset($combineData[$mediaAttribute]) && isset($productStandard['values'][$combineData[$mediaAttribute]]) ? $productStandard['values'][$combineData[$mediaAttribute]] : "";
                        $convertedImage = $this->convertRelativeUrlToBase64(
                            $productStandard['values'][$mediaAttribute],
                            $altData,
                            $position,
                            $imageRoles
                        );
                        if ($convertedImage) {
                            $position++;
                            $productStandard['media_gallery_entries'][] = $convertedImage;
                            if ($productImageRoles) {
                                $productStandard['media_gallery_entries']['image_roles'][$convertedImage['content']['name']] = $productImageRoles;
                            } elseif ($childImageRoles) {
                                $productStandard['media_gallery_entries']['child_image_roles'][$convertedImage['content']['name']] = $childImageRoles;
                            }
                        }
                    }
                }
                unset($productStandard['values'][$mediaAttribute]);
            }

            $videoAttributes = !empty($otherMappings['videoAttrsMapping']) ? $otherMappings['videoAttrsMapping'] : [];
            foreach ($videoAttributes as $index => $videoAttribute) {
                if (isset($videoAttribute['video']) && isset($videoAttribute['video_images'])) {
                    $videoUrlAttr = $videoAttribute['video'];
                    $videoImageAttr = $videoAttribute['video_images'];
                    if (in_array($videoImageAttr, $mediaAttributes)) {
                        continue;
                    }

                    if ($this->isSameLevelImage($videoUrlAttr, $productStandard)) {
                        if (!empty($productStandard['values'][$videoUrlAttr])
                            && !empty($productStandard['values'][$videoImageAttr])
                        ) {
                            $position = count($productStandard['media_gallery_entries']) ?? $index + 1;
                            $videoUrls = $productStandard['values'][$videoUrlAttr];
                            $videoImages = $productStandard['values'][$videoImageAttr];
                            $productStandard = $this->formateMediaGalleryEntities($videoUrls, $videoImages, $productStandard, $position);
                        }
                    }
                }
            }
        }

        /* add attribute to process */
        $attributeMappings = $this->connectorService->getAttributeMappings();
        if ($productStandard['type_id'] === 'variant') {
            $commonMapping = $attributeMappings;
            $attributeMappings = $this->connectorService->getSettings('magento2_child_attribute_mapping');
            $attributeMappings = array_merge(array_diff_key($commonMapping, $attributeMappings), $attributeMappings);
        }

        $flippedMappings = array_flip($attributeMappings);
        $productStandard[Prop::FIELD_META_DATA]['unprocessed'] = [];
        $productStandard['custom_attributes'] = [];
        if (!empty($productStandard[Prop::FIELD_META_DATA][Prop::FIELD_IDENTIFIER])) {
            if (isset($productStandard[Prop::FIELD_ENABLED])) {
                $productStandard['status'] = $productStandard[Prop::FIELD_ENABLED];
            }
        }

        /* custom attributes indexing */
        foreach ($productStandard['values'] as $field => $value) {
            if (!$recursiveCall || $this->isSameLevelAttribute($field, $productStandard) || in_array($field, ['categories'])) {
                
                /* sku */
                if ($field == 'sku' || $field == 'SKU') {
                    $productStandard['sku'] = $this->formatValueForMagento($value);
                /* mapped attributes (standard) */
                } elseif (in_array($field, array_values($attributeMappings))) {
                    if (isset($flippedMappings[$field])) {
                        switch ($flippedMappings[$field]) {
                            case 'quantity':
                            case 'website_ids':
                                $productStandard[$flippedMappings[$field]] = $value;
                                $productStandard[Prop::FIELD_META_DATA]['unprocessed'][] = $flippedMappings[$field];
                                break;
                            case 'status':
                                $value = intval($this->formatValueForMagento($value));
                                break;
                            case 'visibility':
                                // $value = $this->formatValueForMagento($value);
                                // $newValue = $this->connectorService->searchOptionsValueByCode($value, $flippedMappings[$field]);
                                // if ($newValue) {
                                //     $value = intval($newValue);
                                // } else {
                                //     $value = intval($value);
                                // }
                                // if ($field == 'visibility' && !$value) {
                                //     break;
                                // }
                            /* base attributes */
                            case in_array($flippedMappings[$field], $this->magentoBaseFields) ? true :false:
                                $productStandard[$flippedMappings[$field]] = $value;
                                $productStandard[Prop::FIELD_META_DATA]['unprocessed'][] = $flippedMappings[$field];
                                break;
                                
                            case in_array($flippedMappings[$field], $this->stockItemFields) ? true : false:
                                $productStandard[$flippedMappings[$field]] = $value;
                                $productStandard[Prop::FIELD_META_DATA]['unprocessed'][] = $flippedMappings[$field];
                                break;
                            /* standard/custom attributes */
                            default:
                                $productStandard['custom_attributes'][] = [
                                    'attribute_code' => $flippedMappings[$field],
                                    'value'          => $value,
                                ];
                        }
                    }

                    /* custom mapped attributes */
                } elseif (!empty($otherMappings['custom_fields']) && in_array($field, $otherMappings['custom_fields'])) {
                    $customValue = [
                        'attribute_code' => $field,
                        'value'          => $value,
                    ];
                    $productStandard['custom_attributes'][] = $customValue;

                    if (in_array($field, $this->stockItemFields)) {
                        $productStandard[$field] = $value;
                        $productStandard[Prop::FIELD_META_DATA]['unprocessed'][] = $field;
                    }

                    /* meta data fields */
                } elseif (in_array($field, $this->magentoNoncustomValuesArray)) {
                    $productStandard[Prop::FIELD_META_DATA][$field] =  $value;
                    unset($productStandard[$field]);
                } elseif ($this->vendorReferenceSelect && $field == $this->vendorReferenceSelect) {
                    $productStandard[Prop::FIELD_META_DATA]['vendor'] = $this->formatValueForMagento($value);
                }

                if (!empty($otherSettings['fallbackName']) && $field == $otherSettings['fallbackName']) {
                    $productStandard[Prop::FIELD_META_DATA]['fallbackName'] = $value;
                }
            }
        }

        if (empty($productStandard['sku']) && !empty($productStandard[Prop::FIELD_META_DATA][Prop::FIELD_IDENTIFIER])) {
            $productStandard['sku'] = $productStandard[Prop::FIELD_META_DATA][Prop::FIELD_IDENTIFIER];
        }
        
        $productStandard = $this->supportForTierPricingOptions($productStandard);
        $productStandard = $this->supportForBundleDiscountOptions($productStandard);
        
        /** Customization magento tier field addon export start */
        $this->supportForTierPriceFieldAddOn($productStandard);
        /** Customization magento tier field addon export end*/

        unset($productStandard['values']);
        if (!$recursiveCall) {
            $this->detacher->detach($product);
        }
 
        return $productStandard;
    }

    /**
     * Support for the Tier Pricing Options. It add the tierpricing in the prodcut array.
     * @param array $productStandard
     * @return array $productStandard
     **/
    protected function supportForTierPricingOptions(array $productStandard): array
    {
        $price = 0;
        $discount = 0;
        if (!empty($productStandard[Prop::FIELD_META_DATA][Prop::TIER_PRICING_OPTIONS])) {
            $tierPricingOptions = $productStandard[Prop::FIELD_META_DATA][Prop::TIER_PRICING_OPTIONS];
            
            if (is_array($tierPricingOptions)) {
                foreach ($tierPricingOptions as $tierPricingOption) {
                    if ($tierPricingOption['percentage_value'] == 1) {
                        $price = $tierPricingOption['value'];
                        $discount = 0;
                    } elseif ($tierPricingOption['percentage_value'] == 2) {
                        $price = 0;
                        $discount = $tierPricingOption['value'];
                    }
                    $value = [
                        "customer_group_id" =>  $tierPricingOption['customer_group_id'] ?? 32000,
                        "qty" => $tierPricingOption['qty'] ?? '',
                        "value" => $price,
                        "extension_attributes" => [
                            "website_id" => $tierPricingOption['website_id'] ?? 0,
                        ]
                    ];
                    if (isset($tierPricingOption['percentage_value'])) {
                        $value["extension_attributes"]['percentage_value'] = $discount;
                    }

                    $productStandard['tier_prices'][] = $value;
                }
            }
        }

        return $productStandard;
    }

    /**
    * Support for the Tier Pricing akeneo field addon
    *
    * @param array $productStandard
    **/
    protected function supportForTierPriceFieldAddOn(array &$productStandard)
    {
        if ($this->connectorService->isSupportFor('support_magento2_tier_price')) {
            $tierPriceData = [];
            if (isset($productStandard['values']['tier_pricing'])) {
                $tierPrice = $this->attributeRepository->findOneByIdentifier('tier_pricing');
                if ($tierPrice
                    && $tierPrice->getAttributeType() == 'pim_catalog_tier_pricing'
                ) {
                    $tierPriceData = $productStandard['values']['tier_pricing'];
                    foreach ($tierPriceData as $tierPrice) {
                        foreach ($tierPrice['data'] as $mainTierPrice) {
                            if ($mainTierPrice['price_type'] == 1) {
                                $price = $mainTierPrice['value'];
                                $discount = 0;
                            } else {
                                $price = 0;
                                $discount = $mainTierPrice['value'];
                            }

                            $value = [
                                "customer_group_id" =>  $mainTierPrice['customer_group_id'],
                                "qty" => (int) $mainTierPrice['qty'],
                                "value" => $price,
                                "extension_attributes" => [
                                    "website_id" => $mainTierPrice['website'],
                                ]
                            ];

                            if ($mainTierPrice['price_type'] == 2) {
                                $value["extension_attributes"]['percentage_value'] = $discount;
                            }
                            
                            $productStandard['tier_prices'][] = $value;
                        }
                    }
                }
            }
        }
    }
    
    /**
    * Support for the Bundle Discount Option. It add the Bundle Discount Option in the prodcut array.
    * @param array $productStandard
    * @return array $productStandard
    **/
    protected function supportForBundleDiscountOptions(array $productStandard): array
    {
        if (!empty($productStandard[Prop::FIELD_VALUES][Prop::BUNDLE_DISCOUNT_ATTRIBUTE])) {
            $bundleDiscountOptions = $productStandard[Prop::FIELD_VALUES][Prop::BUNDLE_DISCOUNT_ATTRIBUTE];
            if (is_array($bundleDiscountOptions)) {
                foreach ($bundleDiscountOptions as $bundleDiscountOption) {
                    $value = [];
                    if (!empty($bundleDiscountOption['data'])) {
                        foreach ($bundleDiscountOption['data'] as $bundleDiscountDataRow => $bundleDiscountData) {
                            $value[$bundleDiscountDataRow][$bundleDiscountData['bundle_name']] = [
                                "bundlediscount_option_name" =>  $bundleDiscountData['bundle_name'],
                                "bundlediscount_option_qty" =>  $bundleDiscountData['qty'],
                                "bundlediscount_option_delete" =>  '',
                                "datepicker" =>  $bundleDiscountData['enable_from'],
                                "bundlediscount_option_date_to" =>  $bundleDiscountData['enable_to'],
                                "bundlediscount_option_bundle_option" =>  '',
                                "bundlediscount_option_discount_type" =>  $bundleDiscountData['discount_type'],
                                "bundlediscount_option_discount_price" =>  $bundleDiscountData['value'],
                                "bundlediscount_option_exclude_base_product" =>  $bundleDiscountData['ignore_base_product_discount'],
                                "products" => $bundleDiscountData['products'],
                                "baseSku" =>$productStandard[Prop::FIELD_META_DATA][Prop::FIELD_IDENTIFIER]
                            ];
                        }
                    }
                    $productStandard[Prop::BUNDLE_PRODUCT_OPTIONS][] = $value;
                }
            }
        }

        return $productStandard;
    }
    
    /**
     * Create the video format array for Magento API.
     *
     * @param $videoUrls
     * @param $videoImageAttr
     * @param $productStandard
     * @param $index = 0
     *
     */
    public function formateMediaGalleryEntities($videoUrls, $videoImageAttr, $productStandard, $index = 0)
    {
        if (is_array($videoUrls) && !empty($videoUrls) && is_array($videoImageAttr) && !empty($videoImageAttr)) {
            foreach ($videoUrls as $key => $data) {
                $image = ($videoImageAttr[$key]['locale'] == $data['locale'])? $videoImageAttr[$key]['data']: "";
                $index++;
                $imagesRoles = $index ? [] : ['base_image', 'small_image', 'thumbnail_image'];
                $convertedImage = $this->convertRelativeUrlToBase64($image, null, $index++, $imagesRoles);
                if ($convertedImage) {
                    $convertedImage['media_type'] = 'external-video';
                    $convertedImage['disabled'] = true;
                    $convertedImage['store_view_locale'] = $data['locale'];
                    $convertedImage['extension_attributes'] = [
                        "video_content" => [
                            "media_type" => "external-video",
                            "video_provider" => "",
                            "video_url" => $data['data'],
                            "video_title" => $data['data'],
                            "video_description" => '',
                            "video_metadata" => ''
                        ]
                    ];
                    $productStandard['media_gallery_entries'][] = $convertedImage;
                }
            }
        }

        return $productStandard;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setStepExecution(\StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
        $credentials = $this->connectorService->getCredentials();
        if (isset($credentials['storeMapping'])
            && isset($credentials['storeMapping']['all'])
        ) {
            $this->channel= $credentials['storeMapping']['all']['channel'];
            $this->locale = $credentials['storeMapping']['all']['locale'];
        }
    }

    /**
     * Check the Images is same level or not. like product model image is the samelevel with the variant product.
     * @param $attrCode
     * @param $productStandard
     * @return $flag
     */
    protected function isSameLevelImage($attrCode, $productStandard)
    {
        if (!empty($productStandard[Prop::FIELD_PARENT])) {
            $childLevelAttribute = $productStandard[Prop::VARIANT_ATTRIBUTES];
            $flag = in_array($attrCode, $childLevelAttribute);
        } else {
            $flag = $this->isSameLevelAttribute($attrCode, $productStandard);
        }

        return $flag;
    }

    /**
     * Check the attribute is same level or not.
     * @param $attrCode
     * @param $productStandard
     * @return $flag
     */
    protected function isSameLevelAttribute($attrCode, $productStandard)
    {
        $flag = isset($productStandard[Prop::VARIANT_ATTRIBUTES]) && !in_array($attrCode, $productStandard[Prop::VARIANT_ATTRIBUTES]);

        return !$flag;
    }


    /**
     * Filters the attributes that have to be exported based on a product and a list of attributes
     *
     * @param array $values
     * @param array $attributesToFilter
     *
     * @return array
     */
    protected function filterValues(array $values, array $attributesToFilter)
    {
        $valuesToExport = [];
        $attributesToFilter = array_flip($attributesToFilter);
        foreach ($values as $code => $value) {
            if (isset($attributesToFilter[$code])) {
                $valuesToExport[$code] = $value;
            }
        }

        return $valuesToExport;
    }

    /**
     * Formate the value for the magento based on the localizable and scopable value.
     *
     * @param $value
     * @return #value
     */
    private function formatValueForMagento($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $data) {
                if ($this->channel && isset($data['scope']) &&  $data['scope'] !== $this->channel) {
                    continue;
                }

                if ($this->locale && isset($data['locale']) &&  $data['locale'] !== $this->locale) {
                    continue;
                }

                $value = !empty($data['data']) ? $data['data'] : null;
            }
        }
        
        return $value;
    }

    protected function convertRelativeUrlToBase64($entry, $mediaAltText = '', $position = 0, $imageRoles)
    {
        try {
            $context = $this->router->getContext();
            $credendial = $this->connectorService->getCredentials();

            if (!empty($credendial['host'])) {
                $context->setHost($credendial['host']);
            }

            if (isset($credendial['storeMapping']) && isset($credendial['storeMapping']['allStoreView']) && !empty($credendial['storeMapping']['allStoreView']['locale'])) {
                $defaultLocale = $credendial['storeMapping']['allStoreView']['locale'];
            }
            
            if (is_array($entry)) {
                if (empty($entry[0]['data'])) {
                    return;
                }
                foreach ($entry as $value) {
                    if ($value['locale'] === $defaultLocale) {
                        $entry = $value['data'];
                        break;
                    } elseif ($value['locale'] === null) {
                        $entry = $value['data'];
                        break;
                    }
                }
            }
            
            $filename = explode('/', $entry);
            $filename = end($filename);

            $imageContent = $this->connectorService->getImageContentByPath($entry);
            $mimetype = $this->getImageMimeType($imageContent);
            $imageContent = base64_encode($imageContent);
        } catch (\Exception $e) {
            return;
        }

        $label = null;
        if (is_array($mediaAltText)) {
            foreach ($mediaAltText as $value) {
                if ($value['locale'] === $defaultLocale) {
                    $label =  $value['data'];
                    break;
                } elseif ($value['locale'] ===  null) {
                    $label =  $value['data'];
                    break;
                }
            }
        }
        
        $convertedItem = [
            'media_type' => 'image',
            'label'      => $label,
            'position'   => $position,
            'disabled'   => false,
            'types'      => $position ? [] : ["image", "small_image", "thumbnail"],
            'content'    => [
                'base64_encoded_data' => $imageContent,
                'type'                => $this->guessMimetype($mimetype) ? : 'image/png',
                'name'                => substr($filename, 0, 85)
            ],
        ];
        
        
        return $convertedItem;
    }

    /**
     * Return a list of attributes to export
     *
     * @param JobParameters $parameters
     *
     * @return array
     */
    protected function getAttributesToFilter(\JobParameters $parameters)
    {
        $attributes = $parameters->get('filters')['structure']['attributes'];
        $identifierCode = $this->attributeRepository->getIdentifierCode();
        if (!in_array($identifierCode, $attributes)) {
            $attributes[] = $identifierCode;
        }

        return $attributes;
    }

    /**
     * Are there attributes to filters ?
     *
     * @param JobParameters $parameters
     *
     * @return bool
     */
    protected function areAttributesToFilter(\JobParameters $parameters)
    {
        return isset($parameters->get('filters')['structure']['attributes'])
            && !empty($parameters->get('filters')['structure']['attributes']);
    }

    /* add value fillers for product */
    protected function fillProductValues(&$product)
    {
        $product['attribute_set_id']  = 4;
    }

    protected function guessMimetype($ext)
    {
        $mimeArray = [
            'png'  => 'image/png',
            'jpe'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg'  => 'image/jpeg',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'ico'  => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif'  => 'image/tiff',
            'svg'  => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'psd' => 'image/vnd.adobe.photoshop',
        ];

        return !empty($mimeArray[$ext]) ? $mimeArray[$ext] : '';
    }

    protected $stockItemFields = [
        "qty",
        "is_in_stock",
        "quantity_and_stock_status",
        "is_qty_decimal",
        "use_config_min_qty",
        "min_qty",
        "use_config_min_sale_qty",
        "min_sale_qty",
        "use_config_max_sale_qty",
        "max_sale_qty",
        "use_config_backorders",
        "backorders",
        "use_config_notify_stock_qty",
        "notify_stock_qty",
        "use_config_qty_increments",
        "qty_increments",
        "use_config_enable_qty_inc",
        "enable_qty_increments",
        "use_config_manage_stock",
        "manage_stock",
        "low_stock_date",
        "is_decimal_divided",
        "stock_status_changed_auto",
    ];

    /**
     * Get product in standard product
     *
     * @param array                                   $channelsCode
     * @param array                                   $channelsLocales
     * @param \JobParameters                          $parameters
     * @param \ProductInterface|ProductModelInterface $product
     *
     * @return array
     */
    protected function getStandardFormat(
        array $channelsCode,
        array $channelsLocales,
        \JobParameters $parameters,
        $product
    ): array {
        $productStandard = $this->normalizer->normalize(
            $product,
            'standard',
            [
                'filter_types' => ['pim.transform.product_value.structured'],
                'channels' => $channelsCode,
                'locales'  => array_intersect(
                    $channelsLocales,
                    $this->getFilterLocales($this->stepExecution)
                ),
            ]
        );
        if ($this->areAttributesToFilter($parameters)) {
            $attributesToFilter = $this->getAttributesToFilter($parameters);
            $productStandard['values'] = $this->filterValues($productStandard['values'], $attributesToFilter);
        }

        if ($parameters->has('with_media') && $parameters->get('with_media')) {
            $directory = $this->stepExecution->getJobExecution()->getExecutionContext()
                ->get(\JobInterface::WORKING_DIRECTORY_PARAMETER);

            $this->fetchMedia($product, $directory);
        } else {
            $mediaAttributes = $this->attributeRepository->findMediaAttributeCodes();
            $productStandard['values'] = array_filter(
                $productStandard['values'],
                function ($attributeCode) use ($mediaAttributes) {
                    return !in_array($attributeCode, $mediaAttributes);
                },
                ARRAY_FILTER_USE_KEY
            );
        }

        return $productStandard;
    }
   
    /** Get Image role based on mapping
     *
     * @param string $mediaAttribute
     * @param array  $otherMappings
     * @param bool   $checkVariantImageRole
     *
     * @return array
     */
    protected function getImageRoles($mediaAttribute, $otherMappings, $checkVariantImageRole = false)
    {
        $imageRoles = [];
        foreach ($this->allImageRoles as $imageRoleKey => $imageRole) {
            if ($checkVariantImageRole) {
                $imageRoleKey = 'child_'.$imageRoleKey;
            }
            if (isset($otherMappings[$imageRoleKey]) && $otherMappings[$imageRoleKey] == $mediaAttribute) {
                $imageRoles[] = $imageRole;
            }
        }

        return $imageRoles;
    }
}
