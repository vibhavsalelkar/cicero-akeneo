<?php

namespace Webkul\Magento2Bundle\Connector\ArrayConverter;

use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Traits\ApiEndPointsTrait;
use Webkul\Magento2Bundle\Traits\StepExecutionTrait;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Convert standard format to flat format for product with localized values.
 *
 * @author    ankit yadav <ankit.yadav726@webkul.com>
 * @copyright 2018 Webkul (http://www.webkul.com)
 * @license   http://store.webkul.com/license.html
 */
class ProductConverter implements \ArrayConverterInterface, \StepExecutionAwareInterface
{
    use ApiEndPointsTrait;
    use StepExecutionTrait;
    use DataMappingTrait;

    const AKENEO_ENTITY_NAME = 'product';
    
    /** @var \ArrayConverterInterface */
    protected $converter;

    /** @var \AttributeConverterInterface */
    protected $connectorService;

    protected $localizer;
    protected $otherMappings;
    protected $customFields;
    protected $mediaAttributes;
    protected $attributeMappings;
    protected $storeMappings;
    protected $localizableScopableAttributes;
    protected $defaultStoreView;
    protected $attributeTypes;
    protected $websiteCodes;
    protected $websiteCodesArray;
    protected $assosiationMapping;
    protected $familiesOfVariants;
    protected $variantAttributes = [];
    protected $urlKeys = [];
    protected $attributeOptions = [];
    protected $otherSettings = [];
    protected $oauthClient;
    protected $stepExecution;
    protected $locales;

    protected $multiValueSeparator;

    protected $channelRepository;
    protected $categgoryRepository;
    protected $exportChannelCategory;
    protected $channelCategory = [];
    protected $channel;
    protected $jsonHeaders = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ];

    /**
     * @param \ArrayConverterInterface     $converter
     * @param \AttributeConverterInterface $localizer
     */
    public function __construct(
        \ArrayConverterInterface $converter,
        $localizer,
        Magento2Connector $connectorService,
        \ChannelRepository $channelRepository,
        \CategoryRepositoryInterface $categgoryRepository
    ) {
        $this->converter = $converter;
        $this->localizer = $localizer;
        $this->connectorService = $connectorService;
        $this->channelRepository = $channelRepository;
        $this->categgoryRepository = $categgoryRepository;
    }


    /**
     * {@inheritdoc}
     */
    public function setStepExecution(\StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
        $this->locales = $this->getFilterLocales($this->stepExecution);
    }

    /**
     * {@inheritdoc}
     */
    public function convert(array $productStandard, array $options = [])
    {
        /* filter product step wise  */
        if (!isset($this->productTypeStepNameWise[$this->stepExecution->getStepName()]) || $this->getProductType($productStandard)  !== $this->productTypeStepNameWise[$this->stepExecution->getStepName()]) {
            $this->stepExecution->incrementSummaryInfo('read', -1);
            return;
        }
        
        $this->initialize();
        $resultData = [];
        $locale = $this->storeMappings[$this->defaultStoreView]['locale'] ?? '';
        $scope = $this->storeMappings[$this->defaultStoreView]['channel'] ?? '';
        
        if (isset($this->otherSettings['attrOptionAdminCol']) && $this->otherSettings['attrOptionAdminCol'] == "0") {
            $locale = $this->storeMappings[$this->defaultStoreView]['locale'] ?? '';
        }
    
        $data = $this->formatDataByChannelLocaleAndCurrency(
            $productStandard,
            '',
            $options,
            $locale,
            $this->storeMappings[$this->defaultStoreView]['currency'],
            $scope
        );
        if (!empty($data)) {
            if (empty($data['price'])) {
                $data['price'] = "0.0";
            }
            /** Delete the Product from Magento if SKU Changed at Akeneo to remove the  Duplicacy of Product*/
            if (isset($data['sku'])) {
                $this->connectorService->checkMappingAndRemoveMagentoProduct($data['sku']);
            }

            $resultData[] = $data;

            foreach ($this->storeMappings as $storeViewCode => $storeMapping) {
                $locale = $storeMapping['locale'] ?? '';
                $scope = $storeMapping['channel'] ?? '';
                
                if (!empty($locale) && in_array($locale, $this->locales) && $storeViewCode != 'allStoreView') {
                    $magentoData = $this->formatDataByChannelLocaleAndCurrency(
                        $productStandard,
                        $storeViewCode,
                        $options,
                        $locale,
                        $storeMapping['currency'],
                        $scope
                    );
                    $resultData[] = $magentoData;
                }
            }
        }
        
        return $resultData;
    }


    protected function initialize()
    {
        $params = $this->connectorService->getCredentials();
        if (!$this->oauthClient && isset($params['authToken']) && isset($params['hostName'])) {
            $this->oauthClient = new OAuthClient($params['authToken'], $params['hostName']);
        }
        
        if (!$this->otherMappings) {
            $this->otherMappings = $this->connectorService->getOtherMappings();
        }
        if (!$this->attributeMappings) {
            $this->attributeMappings = $this->connectorService->getAttributeMappings();
        }
        if (!$this->mediaAttributes) {
            $this->mediaAttributes = !empty($this->otherMappings['images']) ? $this->otherMappings['images'] : [];
        }
        if (!$this->customFields) {
            $this->customFields = !empty($this->otherMappings['custom_fields']) ? $this->otherMappings['custom_fields'] : [];
        }
        if (!$this->localizableScopableAttributes) {
            $this->localizableScopableAttributes = $this->connectorService->getLocalizableOrScopableAttributes();
        }
        if (!$this->storeMappings) {
            $this->storeMappings = $this->connectorService->getStoreMapping();
        }

        if (!$this->multiValueSeparator) {
            $this->multiValueSeparator = $this->stepExecution->getJobParameters()->has('multiValueSeparator') ? $this->stepExecution->getJobParameters()->get('multiValueSeparator') : ',';
        }

        if (!$this->defaultStoreView) {
            $storeViewCodes = array_keys($this->storeMappings);
            $this->defaultStoreView = count($storeViewCodes) ? $storeViewCodes[0] : '';
            $credentials = $this->connectorService->getCredentials();
            
            if (!empty($credentials['storeMapping']) && !empty($credentials['storeMapping']['allStoreView'])) {
                foreach ($this->storeMappings as $storeViewCode => $data) {
                    if ($storeViewCode == 'allStoreView') {
                        $this->defaultStoreView = $storeViewCode;
                    }
                }
            }
        }
        if (!$this->attributeTypes) {
            $this->attributeTypes = $this->connectorService->getAttributeAndTypes();
        }

        if (!$this->websiteCodes) {
            $this->websiteCodes = $this->getWebsiteCodes();
        }
        if (!$this->assosiationMapping) {
            $this->assosiationMapping = $this->connectorService->getSettings('magento2_association_mapping');
        }
        if (!$this->otherSettings) {
            $this->otherSettings = $this->connectorService->getSettings();
        }
        
        
        /** export channel wise category*/
        $this->exportChannelCategory = $this->stepExecution->getJobParameters()->has('exportSelectedCategory') ? $this->stepExecution->getJobParameters()->get('exportSelectedCategory') : false;
        if (!$this->channel) {
            $this->channel = $this->stepExecution->getJobParameters()->has('filters') ? $this->stepExecution->getJobParameters()->get('filters')['structure']['scope'] : null;
        }

        if ($this->exportChannelCategory && $this->channel) {
            $channel = $this->channelRepository->findOneByIdentifier($this->channel);
            if ($channel) {
                $defaulCategorry = $channel->getCategory()->getCode();
                if ($defaulCategorry) {
                    $rootCategory = $this->categgoryRepository->findOneByIdentifier($defaulCategorry);
                    $childrenCodes = $this->categgoryRepository->getAllChildrenCodes($rootCategory);
                    $this->channelCategory = !empty($childrenCodes) ? $childrenCodes : [];
                }
            }
        }
    }

    /**
     * returns item identifier
     */
    protected function getItemIdentifier(array $product)
    {
        return isset($product['code']) ? $product['code'] : $product['identifier'];
    }

    /**
     * return product type
     */
    protected function getProductType(array $product)
    {
        $type = 'simple';

        if (isset($product['code'])) {
            $type = 'configurable';
        } elseif (isset($product['identifier'])) {
            $mapping = $this->connectorService->getProductMapping($product['identifier']);
            if ($mapping) {
                $type = $mapping->getType();
            }
        }

        return $type;
    }

    public function formatDataByChannelLocaleAndCurrency($productStandard, $storeViewCode, $options, $locale, $currency, $scope = '')
    {
        $isDefaultStoreView = !$storeViewCode;
        $identifier = $this->getItemIdentifier($productStandard);
        $productType = $this->getProductType($productStandard);
        $familyVariant = $productStandard['family_variant'] ?? null;
        $family = $this->getFamily($productStandard);
        
        if (isset($productStandard['code'])) {
            unset($productStandard['code']);
        }
        
        if (isset($productStandard['family_variant'])) {
            unset($productStandard['family_variant']);
        }

        /* remove images of another level */
        if (isset($productStandard['parent'])) {
            $allVariantAttributes = $this->getVariantAttributes($productStandard['parent']);
            $parentImages = array_diff($this->mediaAttributes, $allVariantAttributes);
            
            foreach ($parentImages as $image) {
                unset($productStandard['values'][$image]);
            }
        }
        
        /** Manage the Configurable product status with the attribute configurable_product_status */
        if ($productType === 'configurable') {
            if (isset($productStandard['values']['configurable_product_status'])) {
                $configurableProductStatus = reset($productStandard['values']['configurable_product_status']);
                $productStandard['enabled'] = $configurableProductStatus['data'] ?? false;
            }
        }

        $magentoData = [
            'sku'                => $identifier,
            'store_view_code'    => $storeViewCode,
            'attribute_set_code' => $family,
            'product_type'       => $productType,
            'product_online'     => !isset($productStandard['enabled']) || $productStandard['enabled'] ? 1 : 2,
            'product_websites'   => $this->websiteCodes,
        ];

        /** Filter category if export chanel catgory option is active*/
        if ($this->exportChannelCategory) {
            $productStandard['categories'] = array_intersect($this->channelCategory, $productStandard['categories']);
        }

        if ($isDefaultStoreView) {
            $isCategoriesNotLinkToProducts = $this->stepExecution->getJobParameters()->has('categoriesLinkToProducts') ? $this->stepExecution->getJobParameters()->get('categoriesLinkToProducts') : false;
            if (!$isCategoriesNotLinkToProducts) {
                $magentoData = array_merge($magentoData, [
                    'categories'        => $this->getCommaSeparatedCategories($productStandard['categories']),
                ]);
            }

            // $this->checkExtraCategoriesAndRemove($identifier, $productStandard['categories']);
        }
        
        // $attributeAsImage = $this->getAttributeAsImageByFamily($family);

        if (isset($productStandard['values'])) {
            $productStandard['values'] = $this->formatByChannelLocaleAndCurrency(
                $productStandard['values'],
                $options['scope'],
                $locale,
                $currency
            );

            $assetProductStandard = [];
            if ($productStandard['values']) {
                $attributeKeys = array_keys($productStandard['values']);
                $assetAttributeCode = $this->connectorService->getAssetAttributeCodes();
                $assetAttributeCodes = array_intersect($attributeKeys, $assetAttributeCode);
                
                foreach ($assetAttributeCodes as $assetCode) {
                    $data = reset($productStandard['values'][$assetCode]);
                    
                    $assetProductStandard[$assetCode] = implode(",", $data['data']);
                }
            }

            $productStandard = $this->converter->convert($productStandard, $options);
            $productStandard = $this->updatePriceAndSelectData($productStandard, $options['scope'], $locale, $currency);
            
            if ($productType == 'simple' && !empty($productStandard['parent'])) {
                $attributeMappings = $this->connectorService->getSettings('magento2_child_attribute_mapping');
                $attributeMappings = array_merge(array_diff_key($this->attributeMappings, $attributeMappings), $attributeMappings);
            } else {
                $attributeMappings = $this->attributeMappings;
            }
            
            /* standard attributes mapping */
            foreach ($attributeMappings as $key => $value) {
                if (!in_array($key, ['is_in_stock', 'visibility', 'country_of_manufacture', 'tax_class_id']) && isset($this->attributeTypes[$value]) && $this->attributeTypes[$value] === 'pim_catalog_simpleselect' && isset($productStandard[$value])) {
                    $productStandard[$value] = $this->connectorService->getOptionLabelByAttributeCodeAndLocale($value, $productStandard[$value], $locale);
                }
                
                if (in_array($key, ['is_in_stock', 'quantity_and_stock_status'])) {
                    if (isset($productStandard[$value])) {
                        $magentoData['is_in_stock'] = in_array($productStandard[$value], ['in_stock']) ? 1 : 0;
                    }
                } elseif ($key == 'tax_class_id') {
                    if (isset($productStandard[$value])) {
                        $tax = $this->getAttributeOptionNameById('tax_class_id', $productStandard[$value]);
                        if ($tax) {
                            $magentoData['tax_class_name'] = $tax;
                        }
                    }
                } elseif (in_array($key, ['visibility', 'country_of_manufacture'])) {
                    if (isset($productStandard[$value])) {
                        $magentoData[$key] = $this->getAttributeOptionNameById($key, $productStandard[$value]) ? : $productStandard[$value] ;
                    }
                } elseif (in_array($key, ['website_ids']) && isset($productStandard[$value])) {
                    $magentoData['product_websites'] = $this->getWebsiteCodesByIds($productStandard[$value]);
                } elseif (in_array($key, $this->standardAttributes) || $key === 'quantity') {
                    if (isset($productStandard[$value])) {
                        $key = ($key == 'quantity' ? 'qty' : $key);

                        if (array_key_exists($key, $this->standardAttrAlias)) {
                            $key = $this->standardAttrAlias[$key];
                        }

                        if (array_key_exists($key, $this->strdAttrConfig)) {
                            $magentoData[$this->strdAttrConfig[$key]] = 0;
                        }

                        $magentoData[$key] = $this->formatValue($key, $productStandard[$value]);
                    }
                }
            }
            
            /* customFieldsMapping */
            foreach ($this->customFields as $field) {
                if (isset($productStandard[$field])) {
                    $fieldType = $this->attributeTypes[$field];
                    if ($isDefaultStoreView || in_array($fieldType, ['pim_catalog_text', 'pim_catalog_textarea', 'pim_catalog_date', 'pim_catalog_number', 'pim_catalog_price_collection'])) {
                        if (in_array($field, $this->standardAttributes)) {
                            $value = $this->formatValue($field, $productStandard[$field]);
                            if (isset($this->attributeTypes[$field]) && $this->attributeTypes[$field] === 'pim_catalog_simpleselect' && $value) {
                                $value = $this->connectorService->getOptionLabelByAttributeCodeAndLocale($field, $value, $locale);
                            }
                            if ($value) {
                                $magentoData[$field] = $value;
                            }
                        } else {
                            if (!isset($magentoData['additional_attributes'])) {
                                $magentoData['additional_attributes'] = '';
                            }

                            if ($productStandard[$field] !== "") {
                                switch ($fieldType) {
                                    case 'pim_catalog_multiselect':
                                        $labels = [];
                                        
                                        $productStandard[$field] = explode(',', $productStandard[$field]);
                                        if (!empty($productStandard[$field])) {
                                            foreach ($productStandard[$field] as $code) {
                                                $labels[] = $this->connectorService->getOptionLabelByAttributeCodeAndLocale($field, $code, $locale);
                                            }
                                        }

                                        $magentoData['additional_attributes'] .= $this->multiValueSeparator . strtolower($field) . '=' . implode($labels, '|') . '';
                                        break;
                                    case 'pim_catalog_boolean':
                                        $value = $this->getAttributeOptionNameById($field, $productStandard[$field] ? 1 : 0);
                                        if ($value) {
                                            $magentoData['additional_attributes'] .= $this->multiValueSeparator . strtolower($field) . '='. ($value);
                                        }
                                        break;
                                    case 'pim_catalog_simpleselect':
                                        $magentoData['additional_attributes'] .= $this->multiValueSeparator . strtolower($field) . '=' . ($this->connectorService->getOptionLabelByAttributeCodeAndLocale($field, $productStandard[$field], $locale));
                                        break;
                                    case 'pim_catalog_image':
                                        break;
                                    case 'pim_catalog_date':
                                        $magentoData['additional_attributes'] .= $this->multiValueSeparator . strtolower($field) . '=' . $this->formatValue($field, $productStandard[$field]);
                                        break;
                                    case 'pim_catalog_metric':
                                    $magentoData['additional_attributes'] .= $this->multiValueSeparator . $field . '=' . round($productStandard[$field], 2);
                                        if (!empty($this->otherSettings['metric_selection']) && !empty($productStandard[$field . '-unit'])) {
                                            $magentoData['additional_attributes'] .= ' ' . $productStandard[$field . '-unit'];
                                        }
                                        break;
                                    default:
                                        $magentoData['additional_attributes'] .= $this->multiValueSeparator . $field . '=' . $productStandard[$field];
                                }
                            }

                            $magentoData['additional_attributes'] = trim($magentoData['additional_attributes'], $this->multiValueSeparator);
                        }
                    }
                }
            }
            
            /* url_prefix setting apply */
            if (!empty($magentoData['url_key']) && $magentoData['product_type'] === 'configurable') {
                if (!empty($this->otherSettings['urlKeyPrefix'])) {
                    $magentoData['url_key'] = $this->connectorService->formatUrlKey($this->otherSettings['urlKeyPrefix'] . $magentoData['url_key']);
                }
            }

            if (!isset($attributeMappings['url_key']) || ($magentoData['product_type'] == 'simple' && !empty($productStandard['parent']) && empty($this->connectorService->getSettings('magento2_child_attribute_mapping')['url_key']))) {
                $urlKeyString = '';
                if (empty($productStandard['parent']) && 'configurable' === $magentoData['product_type']) {
                    $urlKeyString = !empty($magentoData['name']) ? $magentoData['name'] : $magentoData['sku'];
                    $urlKeyString = (!empty($this->otherSettings['urlKeyPrefix']) ? $this->otherSettings['urlKeyPrefix'] : '') . $urlKeyString;
                } elseif (empty($productStandard['parent'])) {
                    $urlKeyString = !empty($magentoData['name']) ? $magentoData['name'] : $magentoData['sku'];
                } elseif ($magentoData['product_type'] == 'simple') {
                    $urlKeyString = $magentoData['sku'];
                }
                if ($urlKeyString) {
                    $magentoData['url_key'] =  $this->connectorService->formatUrlKey($urlKeyString);
                }

                /* check existing url_keys */
                if ($isDefaultStoreView && !empty($magentoData['url_key'])) {
                    if (!in_array($magentoData['url_key'], $this->urlKeys)) {
                        $this->urlKeys[] = $magentoData['url_key'];
                    } else {
                        /* url_key exists */
                        $this->stepExecution->addWarning('Skipping product. url_key already exist for another product', [], new \DataInvalidItem([
                            'url_key' => $magentoData['url_key'],
                            'sku' => $magentoData['sku'],
                        ]));
                        return;
                    }
                }
            }
            
            /* images */
            if (!empty($options['with_media'])) {
                if (empty($magentoData['additional_images'])) {
                    $magentoData['additional_images'] = '';
                }
                
                $mediaAltAttributes = !empty($this->otherMappings['images_alts']) ? $this->otherMappings['images_alts'] : [];
                $mediattributes = !empty($this->otherMappings['images']) ? $this->otherMappings['images'] : [];
                $combineData = count($mediattributes) == count($mediaAltAttributes) ? array_combine($mediattributes, $mediaAltAttributes) : array_combine(array_slice($mediattributes, 0, count($mediaAltAttributes)), $mediaAltAttributes);

                if (!empty($combineData)) {
                    $labels = null;
                    foreach ($combineData as $value) {
                        if (isset($productStandard[$value])) {
                            $labels[] = $productStandard[$value];
                        }
                    }
                    $magentoData['additional_image_labels'] = (is_array($labels) && !empty($labels)) ? implode($this->multiValueSeparator, $labels) : '';
                }

                foreach ($this->mediaAttributes as $image) {
                    if ($isDefaultStoreView || in_array($image, $this->localizableScopableAttributes)) {
                        if (!empty($productStandard[$image])) {
                            $parent = !empty($productStandard['parent']) ? $productStandard['parent'] : false;
                            $this->getImageRoles($magentoData, $image, $productStandard[$image], $parent);
                            $magentoData['additional_images'] .= $this->multiValueSeparator . $productStandard[$image];
                            $magentoData['additional_images'] = ltrim($magentoData['additional_images'], $this->multiValueSeparator);
                        }
                    }
                }
                
                // if(!empty($magentoData['additional_images'])) {
                //     $magentoData['additional_images'] = $this->checkAndValidateImages($magentoData['sku'], $magentoData['additional_images']);
                // }
                
                if (isset($magentoData['additional_images']) && !$magentoData['additional_images']) {
                    unset($magentoData['additional_images']);
                }
            }
        }

        if ($isDefaultStoreView) {
            /** related products
            * @TODO add products in related groups
            */
            foreach (['related', 'crosssell', 'upsell'] as $assosiation) {
                if (isset($this->assosiationMapping[$assosiation]) && isset($productStandard[$this->assosiationMapping[$assosiation] . '-' . 'products'])) {
                    if (empty($magentoData[$assosiation . '_skus'])) {
                        $magentoData[$assosiation . '_skus'] = [];
                    } else {
                        $magentoData[$assosiation . '_skus'] = explode(',', $magentoData[$assosiation . '_skus']);
                    }

                    $magentoData[$assosiation . '_skus'] = array_values(array_filter(array_merge(
                        explode(',', $productStandard[$this->assosiationMapping[$assosiation] . '-' . 'products']),
                        explode(',', $productStandard[$this->assosiationMapping[$assosiation] . '-' . 'product_models']),
                        $magentoData[$assosiation . '_skus']
                    )));
                    $assosiationCount = count($magentoData[$assosiation . '_skus']);
                    $magentoData[$assosiation . '_skus'] = implode(',', $magentoData[$assosiation . '_skus']);
                    
                    $magentoData[$assosiation . '_position'] = $this->createLinearCountString($assosiationCount);
                }
            }

            /* configurable_variations */
            if ($productType == 'configurable' && $familyVariant) {
                $magentoData['configurable_variations'] = '';
                $childs = $this->connectorService->getChildProductsByProductModelCode($magentoData['sku'], $this->variantAttributes[$familyVariant]);

                foreach ($childs as $child) {
                    foreach ($child as $key => $value) {
                        if (isset($this->attributeTypes[$key]) && $this->attributeTypes[$key] === 'pim_catalog_simpleselect') {
                            $value = $this->connectorService->getOptionLabelByAttributeCodeAndLocale($key, $value, $locale);
                        } elseif (isset($this->attributeTypes[$key]) && $this->attributeTypes[$key] === 'pim_catalog_metric') {
                            $valData = explode(" ", $value);
                            $value = round($valData[0], 2)." ".$valData[1];
                        }

                        $magentoData['configurable_variations'] .= ($key . '=' . $value . $this->multiValueSeparator);
                    }
                    $magentoData['configurable_variations'] = trim($magentoData['configurable_variations'], $this->multiValueSeparator) . '|';
                }
                $magentoData['configurable_variations'] = trim($magentoData['configurable_variations'], '|');
            }

            /* bundle products */
            if ($productType == 'bundle') {
                $magentoData['bundle_values'] = $productStandard['bundle_values'] ?? '';
                $magentoData['bundle_shipment_type'] = $this->getAttributeOptionNameById('shipment_type', $productStandard['bundle_shipment_type'] === 'together' ? 0 : 1);
                $magentoData['bundle_price_type'] =  $productStandard['bundle_price_type'] ?? '';
                $magentoData['bundle_sku_type'] = $productStandard['bundle_sku_type'] ?? '';
                $magentoData['bundle_weight_type'] = $productStandard['bundle_weight_type'] ?? '';
                $magentoData['bundle_price_view'] = $this->getAttributeOptionNameById('price_view', $productStandard['bundle_price_view'] === 'Price range' ? 0 : 1);
            }
        }
        /* visibilty child products */
        if (!empty($productStandard['parent']) && isset($productStandard['sku']) && !isset($magentoData['visibility'])) {
            $magentoData['visibility'] = $this->getAttributeOptionNameById('visibility', 1);
        }

        if (empty($magentoData['url_key'])) {
            $magentoData['url_key'] = $magentoData['sku'] ?? '';
        }

        if (isset($attributeMappings['status']) && !empty($attributeMappings['status'])) {
            $magentoData['product_online'] = (isset($productStandard[$attributeMappings['status']]) &&  "1" == $productStandard[$attributeMappings['status']]) ? 1 : 2;
        }

        return $magentoData;
    }

    protected function getCommaSeparatedCategories(array $categories)
    {
        $locale = $this->storeMappings[$this->defaultStoreView]['locale'] ?? 'en_US';

        $categoryString = '';
        foreach ($categories as $categoryCode) {
            $category = $this->connectorService->getCategoryByCode($categoryCode);
            $categoryName = '';

            do {
                if ($locale) {
                    $category->setLocale($locale);
                }
                $categoryLabel = $category->getLabel() && strpos($category->getLabel(), '[') !== 0 ? $category->getLabel() : $category->getCode();
                $categoryName = $categoryName ? $categoryLabel . '/' . $categoryName : $categoryLabel;
                $category = $category->getParent();
            } while ($category);

            $categoryName = trim($categoryName, '/');

            $categoryString = $categoryString ? $categoryString . $this->multiValueSeparator . $categoryName : $categoryName;
        }

        return $categoryString;
    }

    protected function createLinearCountString($no)
    {
        $string = '';
        if ($no) {
            do {
                $string = $no . ',' . $string;
            } while (--$no);
            $string = trim($string, ',');
        }

        return $string;
    }

    protected function getFamily($product)
    {
        $family = null;
        if (isset($product['family'])) {
            $family = $product['family'];
        } elseif (isset($product['family_variant'])) {
            if (empty($this->familiesOfVariants[$product['family_variant']])) {
                $familyVariant = $this->connectorService->getFamilyVariantByIdentifier($product['family_variant']);

                /* save values family_variant */
                $variantAttributes = [];
                foreach ($familyVariant->getAxes() as $axes) {
                    $variantAttributes[] = $axes->getCode();
                }
                $this->variantAttributes[$product['family_variant']] = $variantAttributes;
                /* end save values */

                $this->familiesOfVariants[$product['family_variant']] = $familyVariant ? $familyVariant->getFamily()->getCode() : null;
            }

            $family = $this->familiesOfVariants[$product['family_variant']];
        }

        return $family ? strtolower($family) : '';
    }

    protected function getVariantAttributes($parentCode)
    {
        $result = [];
        $productModel = $this->connectorService->findProductModelByIdentifier($parentCode);
        if ($productModel) {
            $varAttributeSets = $productModel->getFamilyVariant()->getVariantAttributeSets();
            foreach ($varAttributeSets as $attrSet) {
                $attributes = $attrSet->getAttributes();
                foreach ($attributes as $attribute) {
                    $result[] = $attribute->getCode();
                }
            }
        }
        return $result;
    }

    protected function formatValue($attributeCode, $value)
    {
        if (isset($this->attributeTypes[$attributeCode]) && $this->attributeTypes[$attributeCode] === 'pim_catalog_date') {
            $value = $this->formatDate($value);
        }

        if (isset($this->attributeTypes[$attributeCode]) && in_array($this->attributeTypes[$attributeCode], ['pim_catalog_text', 'pim_catalog_textarea'])) {
            $value = str_replace($this->multiValueSeparator, '', $value);
        }

        return $value;
    }

    protected function getWebsiteCodesByIds($ids)
    {
        $result = [];
        if ($ids) {
            if (gettype($ids) === 'string') {
                $ids = explode($this->multiValueSeparator, $ids);
            }
            foreach ($ids as $id) {
                if (isset($this->websiteCodesArray[$id])) {
                    $result[] = $this->websiteCodesArray[$id];
                }
            }
        }

        return implode($this->multiValueSeparator, $result);
    }

    protected function formatDate($date)
    {
        try {
            $dateObj = new \DateTime($date);

            $date = $dateObj->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
        }

        return $date;
    }

    protected function formatByChannelLocaleAndCurrency(array $values, $scope, $locale, $currency)
    {
        foreach ($values as $attributeCode => $attributeValue) {
            if (is_array($attributeValue) && count($attributeValue)) {
                foreach ($attributeValue as $key => $value) {
                    /* not of current scope */
                    if (isset($value['scope']) && $value['scope'] === $scope) {
                        $values[$attributeCode][$key]['scope'] = null;
                    } elseif (isset($value['scope']) && $value['scope'] !== $scope) {
                        unset($values[$attributeCode][$key]);
                        continue;
                    }
                    /* not of current locale */
                    if (isset($value['locale']) && $value['locale'] === $locale) {
                        $values[$attributeCode][$key]['locale'] = null;
                    } elseif (isset($value['locale']) && $value['locale'] !== $locale) {
                        unset($values[$attributeCode][$key]);
                        continue;
                    }
                }
            }
        }

        return $values;
    }

    protected function getAttributeAsImageByFamily($family)
    {
        return $this->connectorService->getAttributeAsImageByFamily($family);
    }


    protected function getWebsiteCodes()
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getWebsites');

        $this->websiteCodesArray = [];
        
        try {
            $this->oauthClient->fetch($url, [], 'GET', $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $results = null;
        }

        if ($results) {
            foreach ($results as $result) {
                if (isset($result['code']) && $result['code'] !== 'admin') {
                    $this->websiteCodesArray[$result['id']] = $result['code'];
                }
            }
        }

        return implode($this->multiValueSeparator, $this->websiteCodesArray);
    }

    protected function getAttributeOptionsByApi($attrCode)
    {
        if (empty($this->attributeOptions[$attrCode])) {
            $url = $this->oauthClient->getApiUrlByEndpoint('getAttributes', 'all');
            $url = str_replace('{attributeCode}', $attrCode, $url);

            try {
                $this->oauthClient->fetch($url, [], 'GET', $this->jsonHeaders);
                $results = json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
                $results = null;
            }

            $options = [];
            if (!empty($results['options'])) {
                foreach ($results['options'] as $result) {
                    if (isset($result['value'])) {
                        $options[$result['value']] = $result['label'];
                    }
                }
            }

            $this->attributeOptions[$attrCode] = $options;
        }

        return $this->attributeOptions[$attrCode] ?? [];
    }

    /**
    * @deprecated "now changes at magento2 end for fost processing"
    */
    protected function checkAndValidateImages($sku, $images)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getProductMedias');
        $url = str_replace('{sku}', $sku, $url);
        try {
            $this->oauthClient->fetch($url, [], 'GET', $this->jsonHeaders);
            $response = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
        }

        if (!empty($response) && is_array($response) && count($response)) {
            $existingImages = array_map(function ($a) {
                return $a['file'] ?? '';
            }, $response);

            $images = explode($this->multiValueSeparator, $images);
            $commonImages = [];
            foreach ($images as $key => $imageName) {
                $tempName = explode('/', urldecode(urldecode($imageName)));
                $name = end($tempName);
                $tempName = explode('.', $name);
                $name = reset($tempName);
                $matches = preg_grep('#' . $name . '#i', $existingImages);
                if (count($matches)) {
                    $commonImages = array_unique(array_merge($commonImages, $matches));
                    unset($images[$key]);
                }
            }

            $extraImages = array_diff($existingImages, $commonImages);
            if ($extraImages) {
                foreach ($response as $image) {
                    if (in_array($image['file'], $extraImages)) {
                        $this->deleteImageByProductSkuAndImageId($sku, $image['id']);
                    }
                }
            }

            $images = implode($this->multiValueSeparator, $images);
        }

        return $images;
    }

    protected function getAttributeOptionNameById($attributeCode, $value)
    {
        $id = $value;
        $mapping = $this->connectorService->getMappingByCode($value . '(' . $attributeCode . ')', 'option');
        
        if ($mapping) {
            $id = $mapping->getExternalId();
        }
        $options = $this->getAttributeOptionsByApi($attributeCode);
        
        return $options[$id] ?? '';
    }

    /**
    * @deprecated "now changes at magento2 end for fost processing"
    */
    protected function checkExtraCategoriesAndRemove($sku, $categories)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getProduct');
        $url = str_replace('{sku}', $sku, $url);
        $url = $url . '?fields=custom_attributes';
            
        try {
            $this->oauthClient->fetch($url, [], 'GET', $this->jsonHeaders);
            $response = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $response = [];
        }

        if (!empty($response['custom_attributes'])) {
            foreach ($response['custom_attributes'] as $attr) {
                if ($attr['attribute_code'] === 'category_ids') {
                    $existingIds = $attr['value'];
                    break;
                }
            }
        }

        /* get new categories */
        $newIds = [];
        foreach ($categories as $code) {
            $mapping = $this->connectorService->getMappingByCode($code, 'category');
            if ($mapping && $mapping->getExternalId()) {
                $newIds[] = $mapping->getExternalId();
            }
        }

        /* remove extra action */
        if (!empty($existingIds) && array_diff($existingIds, $newIds)) {
            $extraCategories = array_values(array_diff($existingIds, $newIds));
            foreach ($extraCategories as $extraCategory) {
                $url = rtrim($params['hostName'], '/') . $this->apiEndpoints['removeCategoryProduct'];
                $url = str_replace('{categoryId}', $extraCategory, $url);
                $url = str_replace('{sku}', $sku, $url);
                
                try {
                    $this->oauthClient->fetch($url, [], 'DELETE', $this->jsonHeaders);
                    $response = json_decode($this->oauthClient->getLastResponse(), true);
                } catch (\Exception $e) {
                    $response = [];
                }
            }
        }
    }

    protected function deleteImageByProductSkuAndImageId($sku, $entryId)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('removeProductMedia');
        $url = str_replace(
            [ '{sku}', '{entryId}' ],
            [ urlencode($sku), urlencode($entryId) ],
            $url
        );
        /* delete extra images */
        
        try {
            $this->oauthClient->fetch($url, null, 'DELETE', $this->jsonHeaders);
            $response = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $response = [];
        }
    }

    

    public function updatePriceAndSelectData($productStandard, $scope, $locale, $currency)
    {
        $scope = $scope[0];
        foreach ($this->attributeTypes as $key => $value) {
            if ($value === 'pim_catalog_price_collection') {
                if (isset($productStandard[$key . '-' . $currency])) {
                    $productStandard[$key] = $productStandard[$key . '-' . $currency];
                    unset($productStandard[$key . '-' . $currency]);
                } elseif (isset($productStandard[$key . '-' . $scope . '-' . $currency])) {
                    $productStandard[$key] = $productStandard[$key . '-' . $scope . '-' . $currency];
                    unset($productStandard[$key . '-' . $scope . '-' . $currency]);
                } elseif (isset($productStandard[$key . '-' . $locale . '-' . $currency])) {
                    $productStandard[$key] = $productStandard[$key . '-' . $locale . '-' . $currency];
                    unset($productStandard[$key . '-' . $locale . '-' . $currency]);
                }
            }
        }

        return $productStandard;
    }

    /* used in productWriter */
    public function generateUrl($url, $params, $absolute)
    {
        return $this->connectorService->generateUrl($url, $params, $absolute);
    }
    

    /**
     *  magento standard attributes other than:
     *   'related_skus', 'related_position', 'crosssell_skus', 'crosssell_position', 'upsell_skus', 'upsell_position'
     *   'base_image', 'small_image', 'thumbnail_image', 'additional_images',
     *    'configurable_variations' sku=MP12-32-Black,color=Black,size=32|sku=MP12-32-Blue,color=Blue
     *   'configurable_variation_labels', 'associated_skus'
     */
    protected $standardAttributes = [
        'sku', 'qty', 'out_of_stock_qty', 'use_config_min_qty',  'is_qty_decimal', 'allow_backorders', 'use_config_backorders', 'min_cart_qty', 'min_sale_qty','use_config_min_sale_qty',  'max_cart_qty',  'max_sale_qty', 'use_config_max_sale_qty', 'is_in_stock', 'notify_on_stock_below', 'use_config_notify_stock_qty', 'manage_stock',
        'use_config_manage_stock', 'use_config_qty_increments', 'qty_increments', 'use_config_enable_qty_inc', 'enable_qty_increments', 'is_decimal_divided',
        'display_product_options_in', 'custom_design', 'custom_layout_update', 'page_layout', 'product_options_container', 'hide_from_product_page', 'custom_options',
        'name', 'description', 'short_description', 'url_key', 'meta_title', 'meta_keywords', 'meta_description', 'base_image_label', 'small_image_label', 'thumbnail_image_label', 'additional_image_labels',
        'tax_class_name', 'visibility', 'country_of_manufacture',
        'map_enabled', 'gift_message_available',
        'weight' , 'price', 'special_price', 'map_price', 'msrp_price',
        'special_price_to_date', 'new_from_date', 'new_to_date', 'custom_design_from', 'custom_design_to',
    ];
   
    protected $productTypeStepNameWise = [
        'bundle_product_export' =>  'bundle',
        'product_export'        =>  'simple',
        'product_model_export'  =>  'configurable'
    ];

    protected $standardAttrAlias = [
        'min_sale_qty' => 'min_cart_qty',
        'max_sale_qty' => 'max_cart_qty',
        'quantity' => 'qty',
    ];

    protected $strdAttrConfig = [
        "min_qty" =>  "use_config_min_qty",
        "max_sale_qty" => "use_config_max_sale_qty",
        "min_sale_qty" => "use_config_min_sale_qty",
        "backorders" => "use_config_backorders",
        "notify_stock_qty" => "use_config_notify_stock_qty",
        "qty_increments" => "use_config_qty_increments",
        "enable_qty_increments" => "use_config_enable_qty_inc",
        "manage_stock" => "use_config_manage_stock",
    ];
    

    /**
     * Get Image role based on mapping
     *
     * @param string $mediaAttribute
     *
     * @return array
     */
    protected function getImageRoles(&$magentoData, $mediaAttribute, $media, $parent)
    {
        if ($parent) {
            if (isset($this->otherMappings['child_base_image']) && $this->otherMappings['child_base_image'] == $mediaAttribute) {
                $magentoData['base_image'] = $media;
            }
            if (isset($this->otherMappings['child_small_image']) && $this->otherMappings['child_small_image'] == $mediaAttribute) {
                $magentoData['small_image'] = $media;
            }
            if (isset($this->otherMappings['child_base_image']) && $this->otherMappings['child_thumbnail_image'] == $mediaAttribute) {
                $magentoData['thumbnail_image'] = $media;
            }
        } else {
            if (isset($this->otherMappings['base_image']) && $this->otherMappings['base_image'] == $mediaAttribute) {
                $magentoData['base_image'] = $media;
            }
            if (isset($this->otherMappings['small_image']) && $this->otherMappings['small_image'] == $mediaAttribute) {
                $magentoData['small_image'] = $media;
            }
            if (isset($this->otherMappings['base_image']) && $this->otherMappings['thumbnail_image'] == $mediaAttribute) {
                $magentoData['thumbnail_image'] = $media;
            }
        }
    }
}
