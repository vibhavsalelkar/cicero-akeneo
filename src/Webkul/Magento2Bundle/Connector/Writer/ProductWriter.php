<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\Traits\ApiEndPointsTrait;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$eventListener = new AkeneoVersionsCompatibility();
$eventListener->checkVersionAndCreateClassAliases();

/**
 * Add products to magento2 Api
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class ProductWriter extends BaseWriter implements \ItemWriterInterface, \InitializableInterface
{
    use DataMappingTrait;
    
    use ApiEndPointsTrait;
    
    const AKENEO_ENTITY_NAME = 'product';
    
    const OTHER_SETTINGS = 'magento2_otherSettings';
   
    protected $attributeRepo;
    
    protected $familyVariantRepo;
    
    // protected $locale;

    protected $locales;

    // protected $storeViewCurrency;

    protected $channels;

    // protected $weightUnit;

    protected $booleanAttributes;

    protected $magentoVersion;

    protected $defaultStoreViewCode;

    protected $otherSettings;

    protected $attributeMappings;

    // protected $identifier;

    protected $multiVendorExport;

    // protected $videoMediaEntries;

    // protected $storeMappings;
    
    // protected $bundleDiscount = [];

    // protected $addProductBundleDiscount;
    /* attribute sets */

    protected $channelRepository;
    
    protected $categgoryRepository;
    
    // protected $exportChannelCategory;
    
    // protected $channelCategory = [];

    // protected $attributeSet;

    protected $channelLocales;

    protected $defaultWebsites;

    protected $storeSettings;

    protected $storesConfig;

    /** @var \DoctrineJobRepository */
    private $jobRepository;

    public function __construct(
        \Doctrine\ORM\EntityManager $em,
        Magento2Connector $connectorService,
        $attributeRepo,
        $familyVariantRepo,
        \ChannelRepository $channelRepository,
        \CategoryRepositoryInterface $categgoryRepository,
        \DoctrineJobRepository $jobRepository,
        $multiVendorExport = null,
        $addProductBundleDiscount = null
    ) {
        $this->em = $em;
        $this->connectorService = $connectorService;
        $this->attributeRepo = $attributeRepo;
        $this->familyVariantRepo = $familyVariantRepo;
        $this->channelRepository = $channelRepository;
        $this->categgoryRepository = $categgoryRepository;
        $this->jobRepository = $jobRepository;
        $this->multiVendorExport = $multiVendorExport;
        $this->addProductBundleDiscount = $addProductBundleDiscount;
    }

    public function initialize()
    {
        if (!$this->channels) {
            $this->channels = $this->getChannelScope($this->stepExecution);
        }
        
        if (!$this->locales) {
            $this->locales = $this->getFilterLocales($this->stepExecution);
        }

        if (!$this->otherSettings) {
            $this->otherSettings = $this->connectorService->getSettings();
        }

        if (!$this->magentoVersion) {
            $this->magentoVersion = $this->connectorService->getMagentoVersion2();
        }

        if (!$this->attributeMappings) {
            $this->attributeMappings = $this->connectorService->getAttributeMappings();
        }

        /** export channel wise category*/
        $this->exportChannelCategory = $this->stepExecution->getJobParameters()->has('exportSelectedCategory') ? $this->stepExecution->getJobParameters()->get('exportSelectedCategory') : false;

        if ($this->exportChannelCategory) {
            $channel = $this->channelRepository->findOneByIdentifier($this->channels);
            if ($channel) {
                $defaulCategorry = $channel->getCategory()->getCode();
                if ($defaulCategorry) {
                    $rootCategory = $this->categgoryRepository->findOneByIdentifier($defaulCategorry);
                    $childrenCodes = $this->categgoryRepository->getAllChildrenCodes($rootCategory);
                    $this->channelCategory = !empty($childrenCodes) ? $childrenCodes : [];
                }
            }
        }

        if (!empty($this->multiVendorExport)) {
            $this->multiVendorExport->setOauthClient($this->oauthClient);
            $this->multiVendorExport->setStepExecution($this->stepExecution);
        }

        // $this->getStoreMapping() = $this->getStoreMapping();
       
        $this->storeSettings = $this->connectorService->getOtherSettings();

        
        
        $this->defaultWebsites = array_unique(array_column($this->getStoreMapping(), 'website_id'));
        $this->defaultWebsites = array_diff($this->defaultWebsites, [self::DEFAULT_STORE_VIEW_CODE]);
    }

    /**
     * write products to magento2 Api
     */
    public function write(array $items)
    {
        if ($this->stepExecution->getJobExecution()->getJobInstance()->getJobName() != 'magento2_quick_export') {
            if (!in_array($this->defaultLocale, $this->getParameters()['filters']['structure']['locales'])) {
                $this->stepExecution->addWarning('Invalid Job', [], new \DataInvalidItem([$this->defaultLocale. ' default store view locale is not added in job']));
                $this->stepExecution->setTerminateOnly();
                $items = [];
            }
        }
       
        if (!$this->oauthClient) {
            $this->stepExecution->addWarning('invalid oauth client', [], new \DataInvalidItem([]));
            return;
        }

        static $productModel;
       
        foreach ($items as $key => $mainItem) {
            $this->updateStepExecution($this->stepExecution);

            $iteration = 0;
            $itemResult = null;
            $parentMapping = null;
             
            if (!empty($mainItem['parent']['sku'])) {
                $parentMapping  = $this->getParentMappingBySku($mainItem['parent']['sku']);
            }
            $storeMappings = $this->getStoreMapping();
            if ($this->stepExecution->getJobExecution()->getJobInstance()->getJobName() != 'magento2_quick_export') {
                $storeMappings = $this->updateStoreMappingValueByLocalesChannels($storeMappings, $this->locales, $this->channels);
            }
            
            foreach ($storeMappings as $storeViewCode => $storeMapping) {
                /* skip, if locale is not mapped in store mapping and locale is not present in job fillter locales */
                
                $locale =  $storeMapping['locale'];
                
                $channel = $storeMapping['channel'];

                if (self::DEFAULT_STORE_VIEW_CODE === $storeViewCode) {
                    $storeViewCode = 'all';
                }
                
                if (!empty($mainItem['website_ids'])) {
                    $website_ids = reset($mainItem['website_ids']);
                                       
                    if (!empty($website_ids['data'])) {
                        $storeWebsiteId = null;
                        if($this->storesConfig) {
                            foreach ($this->storesConfig as $storeConfig) {
                                if (isset($storeConfig['code']) && $storeConfig['code'] === $storeViewCode) {
                                    $storeWebsiteId = $storeConfig['website_id'];
                                    break;
                                }
                            }
                        }
                        if (!empty($storeWebsiteId) && !in_array($storeWebsiteId, $website_ids['data'])) {
                            continue;
                        }
                    }
                }
                
                if (isset($mainItem['metadata']['identifier'])) {
                    // CHANGE THE PRODUCT TYPE ('bundled', 'grouped', 'product') AS PER PRODUCT MAPPING
                    $mapping = $this->connectorService->getProductMapping($mainItem['metadata']['identifier']);
                    
                    if ($mapping) {
                        $mainItem[PropertiesNormalizer::FIELD_MAGENTO_PRODUCT_TYPE] =  $mapping->getType();
                    }
                }

                $baseCurrency = !empty($this->storeSettings[$storeViewCode]['base_currency_code']) ? $this->storeSettings[$storeViewCode]['base_currency_code'] : null;
                $storeViewCurrency = !empty($storeMapping['currency']) ? $storeMapping['currency'] : $baseCurrency;
                // $weightUnit = !empty($this->storeSettings[$storeViewCode]['weight_unit']) ? $this->storeSettings[$storeViewCode]['weight_unit'] : null;

                $item = $this->formatData($mainItem, $channel, $locale, $storeViewCurrency);
               
                /**
                 * Delete the Product from Magento
                 * If SKU Changed at Akeneo to remove the  Duplicacy of Product
                 **/
                if (isset($item['sku'])) {
                    $this->connectorService->checkMappingAndRemoveMagentoProduct($item['sku']);
                }
 
                if (empty($item['extension_attributes']['website_ids'])) {
                    $item['extension_attributes']['website_ids'] = $this->defaultWebsites;
                }
                
                switch ($item[PropertiesNormalizer::FIELD_MAGENTO_PRODUCT_TYPE]) {

                    case 'simple':
                    case 'virtual':
                    case 'grouped':
                        
                        $product = [self::AKENEO_ENTITY_NAME => $item];
                        
                        $itemResult = $this->addProduct($product, false, $storeViewCode);
                        
                        break;
                    case 'variant':
                                   
                        $item[PropertiesNormalizer::FIELD_MAGENTO_PRODUCT_TYPE] = PropertiesNormalizer::SIMPLE_TYPE;
                        
                        $parent = $item['parent'] ?? null;
                        unset($item['parent']);

                        /* add product model */
                        if (!$parentMapping) {
                            $productModel = $product = $this->addProduct([self::AKENEO_ENTITY_NAME => $parent], false, $storeViewCode); /* parent product */
                            if (!empty($product['id'])) {
                                $this->addMappingByCode($parent['sku'], $product['id']);
                                if (!$iteration) {
                                    $this->quickExportIncrementById($parent['sku']);
                                }
                            }
                        }
                        
                        $itemResult = $this->addProduct([self::AKENEO_ENTITY_NAME => $item], $parent, $storeViewCode);   /* child */
                        if ($iteration == (-1+count($this->getStoreMapping()))) {
                            $childSku  = $item['sku'];
                            $parentSku = $parent['sku'];
                            if ($itemResult && empty($itemResult['error'])) {
                                $this->linkConfigurableChildToParent($childSku, $parentSku);

                                if (!in_array($this->magentoVersion, ["2.1.7"]) && $this->magentoVersion < "2.2.4") {
                                    $this->updateProduct($productModel); /* parent product */
                                }
                            }
                        }

                        break;
                    case 'bundle':
                        $product = [self::AKENEO_ENTITY_NAME => $item];
                        $itemResult = $this->addProduct($product, false, $storeViewCode);
                        break;
                    case 'downloadable':
                        $product = [self::AKENEO_ENTITY_NAME => $item];
                        $itemResult = $this->addProduct($product, false, $storeViewCode);
                        break;
                }

                $iteration++;
            }
            
            if (isset($itemResult) && !isset($itemResult['error']) && !isset($itemResult['message'])) {
                if ($this->multiVendorExport) {
                    $this->multiVendorExport->handleExportedProductForVendor($mainItem, $itemResult);
                }

                $productMapping = $this->getMappingByCode($item['sku'], 'product');
                if ($productMapping) {
                    $this->stepExecution->incrementSummaryInfo('update');
                } else {
                    $this->stepExecution->incrementSummaryInfo('write');
                }

                
                // Add to Mapping in Database
                $externalId = !empty($itemResult['id']) ? $itemResult['id'] : null;
                $relatedId = !empty($itemResult['attribute_set_id']) ? $itemResult['attribute_set_id'] : null;
                $code = !empty($itemResult['sku']) ? $itemResult['sku'] : null;
                if ($code && $externalId) {
                    $mapping = $this->addMappingByCode($code, $externalId, $relatedId, $this::AKENEO_ENTITY_NAME);
                    /** Support for the Bundle Discount Options start*/
                    if ($this->connectorService->isSupportFor('support_magento_bundle_discount') && !empty($this->bundleDiscount)) {
                        if ($this->addProductBundleDiscount) {
                            $bundleDiscountData = $this->bundleDiscount[PropertiesNormalizer::BUNDLE_PRODUCT_OPTIONS];
                            
                            $this->addProductBundleDiscount->addBundleDiscount($bundleDiscountData, $this->oauthClient, $this->stepExecution, $this->jsonHeaders, $this->getHostName(), $code);
                        }
                        $this->bundleDiscount = [];
                    }
                    /** Support for the Bundle Discount Options end*/
                }
            } else {
                if (isset($itemResult['message'])) {
                    $this->stepExecution->addWarning(
                        $itemResult['message'],
                        [],
                        new \DataInvalidItem([
                            'code' => !empty($mainItem['sku']) ? $mainItem['sku'] : '',
                            'debugLine' => __LINE__
                        ])
                    );
                }
                $this->stepExecution->incrementSummaryInfo('skipped');
                if (null === $itemResult) {
                    $this->stepExecution->addWarning(
                        'Selected Job Locales Not mapped in the Store Mapping Section',
                        [],
                        new \DataInvalidItem([
                            'job Locales' => $this->locales,
                            'store Mapping Locales' => array_column($storeMappings, 'locale'),
                            'debugLine' => __LINE__
                        ])
                    );
                }
            }
            
            if (!empty($this->videoMediaEntries) && isset($item['sku'])) {
                $this->addProductMedias($this->videoMediaEntries, $item['sku']);
                $this->videoMediaEntries = [];
            }
        }

        if ($this->multiVendorExport) {
            $this->multiVendorExport->flushVendorData();
        }
    }

    protected function formatData($item, $channel, $locale, $storeViewCurrency)
    {
        $attributeMappings = $this->changeAttributeMappingsBasedOnProductType($item['type_id'] ?? null);

        $formatted = [
            'custom_attributes' => []
        ];
        
        
        $formatted['main_image_data'] = $item[PropertiesNormalizer::FIELD_META_DATA]['main_image_data'] ?? '';
        
        foreach ($item[PropertiesNormalizer::FIELD_META_DATA]['unprocessed'] as $index) {
            if ($index === 'configurable_product_status' && isset($item['type_id']) && $item['type_id'] !== 'configurable') {
                continue;
            }
             
            if (isset($item[$index])) {
                $val = $this->formatValueForMagento($item[$index], $channel, $locale, $storeViewCurrency, $index);
                
                if ($index == 'weight') {
                    $val = (float)$val;
                } elseif ($index == 'visibility') {
                    if ($val) {
                        $val = (int)$val;
                    }
                }
                
                if ($index === 'configurable_product_status') {
                    $index = 'status';
                    $val = (int) $val;
                }
                
                if (in_array($index, ['sku', 'name', 'price', 'status', 'weight', 'visibility'])) {
                    if ($index === 'status' || $val) {
                        $formatted[$index] = $val;
                    }
                } elseif (in_array($index, array_keys($this->stockItemAttributes))) {
                    if (empty($formatted['extension_attributes'])) {
                        $formatted['extension_attributes'] = [];
                    }
                    if ($index === 'quantity') {
                        $formatted['extension_attributes']['stock_item'] = [
                            'qty' => (string)(int)$val,
                            'is_in_stock' => true, //(boolean)$val
                            'manage_stock' => true,
                        ];
                    } else {
                        if (null !== $val) {
                            $type = $this->stockItemAttributes[$index];
                            if ($type === 'integer') {
                                $formatted['extension_attributes']['stock_item'][$index] = (int) $val;
                            }
                            if ($type === 'boolean') {
                                if ($index === 'is_in_stock' || $index === 'quantity_and_stock_status') {
                                    $index = 'is_in_stock';
                                    if (strcasecmp($val, "in_stock") === 0 || $val === true || $val === 1 || $val === 'ja') {
                                        $formatted['extension_attributes']['stock_item'][$index] = true;
                                    } else {
                                        $formatted['extension_attributes']['stock_item'][$index] = false;
                                    }
                                } else {
                                    $formatted['extension_attributes']['stock_item'][$index] = (boolean) $val;
                                }
                            }
                            
                            //disable Config settings
                            if (in_array($index, array_keys($this->configSetting))) {
                                $formatted['extension_attributes']['stock_item'][ $this->configSetting[$index] ]= 0 ;
                            }
                            if ($index === 'min_qty') {
                                $formatted['extension_attributes']['stock_item']['use_config_min_qty']= 0;
                            }

                            // qty increment present then enable qty increments.
                            if ($index === 'qty_increments') {
                                $formatted['extension_attributes']['stock_item']['enable_qty_increments']= true;
                                $formatted['extension_attributes']['stock_item']['use_config_enable_qty_inc']= false;
                            }
                        }
                    }
                } elseif ($index == 'website_ids') {
                    if (empty($formatted['extension_attributes'])) {
                        $formatted['extension_attributes'] = [];
                    }
                    // Website ids
                    if (isset($this->attributeMappings['website_ids']) && $index == 'website_ids') {
                        $formatted['extension_attributes']['website_ids'] = is_array($val) ? $val : [$val];
                    }
                } else {
                    $item['custom_attributes'][] = [
                        "attribute_code" => $index,
                        "value"          => $val
                    ];
                }
            }
        }

        foreach (['sku', 'status', 'media_gallery_entries', 'extension_attributes', PropertiesNormalizer::FIELD_MAGENTO_PRODUCT_TYPE] as $rawAttribute) {
            if (isset($item[$rawAttribute]) && !isset($formatted[$rawAttribute])) {
                $formatted[$rawAttribute] = $item[$rawAttribute];
            }
        }
       
        /* categories */
        $jobParamters = $this->getParameters();
        if (!isset($jobParamters['categoriesLinkToProducts']) || false === $jobParamters['categoriesLinkToProducts']) {
            $categories = !empty($item[PropertiesNormalizer::FIELD_META_DATA]['categories']) ? $item[PropertiesNormalizer::FIELD_META_DATA]['categories'] : [];
        
            /** Filter category if export chanel catgory option is active*/
            if ($this->exportChannelCategory) {
                $categories = array_intersect($this->channelCategory, $categories);
            }
            $catIds = $this->getcategoryIdsFromCategories($categories);
            
            if (!empty($catIds)) {
                $formatted['custom_attributes'][] = [
                    "attribute_code" => "category_ids",
                    "value"          => $catIds
                ];
            }
        }

        if (!empty($item['custom_attributes'])) {
            foreach ($item['custom_attributes'] as $index => $value) {
                $value['value'] = $this->formatValueForMagento($value['value'], $channel, $locale, $storeViewCurrency);
                if ($value['attribute_code'] === 'url_key' && !empty($this->otherSettings['urlKeyPrefix']) && $item['type_id'] === 'configurable') {
                    $value['value'] = $this->otherSettings['urlKeyPrefix'] . $value['value'];
                }

                if ($value['attribute_code'] === 'special_price' && '' === $value['value']) {
                    $value['value'] = null;
                }

                if (isset($value['attribute_code']) && in_array($value['attribute_code'], $this->getBooleanAttributes())) {
                    $value['value'] = (int)$value['value'];
                }
                $formatted['custom_attributes'][] = $value;
            }
        }

        if (!empty($formatted['custom_attributes'])) {
            $formatted['custom_attributes'] = $this->modifyOptionValues($formatted['custom_attributes'], $attributeMappings);

            foreach ($formatted['custom_attributes'] as $index => $value) {
                $formatted['custom_attributes'][$index]['value'] = $this->typeCastValue($value['attribute_code'], $value['value']);
            }
        }

        $family = $item[PropertiesNormalizer::FIELD_META_DATA]['family'];
        if ($family) {
            $familyMapping = $this->getMappingByCode($family, 'family');

            if ($familyMapping) {
                $formatted['attribute_set_id'] = $familyMapping->getExternalId();
            } else {
                $this->stepExecution->addWarning(
                    'Error! family not exported , export family first. code: ' . $family,
                    [],
                    new \DataInvalidItem([
                        'code' => $family,
                        'debugLine' => __LINE__
                    ])
                );
            }
        }

        if (!empty($item['parent'])) {
            $formatted['parent'] = $this->formatData($item['parent'], $channel, $locale, $storeViewCurrency);
        }

        if (isset($item['axes']) && !empty($formatted['parent'])) {
            $axes = $item['axes'];
            if (!empty($axes)) {
                if (!isset($formatted['parent']['extension_attributes'])) {
                    $formatted['parent']['extension_attributes'] = [];
                }
                
                if (!isset($formatted['parent']['extension_attributes']['stock_item'])) {
                    $formatted['parent']['extension_attributes']['stock_item'] = [
                        'is_in_stock' => true
                    ];
                }

                $formatted['parent']['extension_attributes']['configurable_product_options'] = [];
                foreach ($axes as $attributeCode) {
                    $mapping = $this->getMappingByCode($attributeCode, 'attribute');
                    if ($mapping) {
                        $customAttr = [
                            'attribute_id' =>  $mapping->getExternalId(),
                            'label' => $attributeCode,
                            'position' =>  0,
                            'is_use_default' => true,
                            'values' => [
                            ]
                        ];
                        $optionMappings = $this->getOptionsByAttributeCode($attributeCode);
                        if ($optionMappings) {
                            foreach ($optionMappings as $optionMapping) {
                                if ('' !== $optionMapping->getExternalId()) {
                                    $customAttr['values'][] = [
                                        'value_index' => $optionMapping->getExternalId(),
                                    ];
                                }
                            }
                        }

                        $formatted['parent']['extension_attributes']['configurable_product_options'][] = $customAttr;
                    }
                }
            }
        }

        if (!array_key_exists('name', $formatted)) {
            if (!empty($item[PropertiesNormalizer::FIELD_META_DATA]['fallbackName'])) {
                $value = $this->formatValueForMagento($item[PropertiesNormalizer::FIELD_META_DATA]['fallbackName'], $channel, $locale, $storeViewCurrency);
                $formatted['name'] = $value;
            }
        }

        if (!isset($attributeMappings['url_key'])
            || (
                $item['type_id'] == 'variant'
                && empty($this->connectorService->getSettings('magento2_child_attribute_mapping')['url_key'])
            )
        ) {
            $urlKeyString = '';
            if (empty($item['parent']) && 'configurable' === $item['type_id']) {
                $urlKeyString = !empty($formatted['name']) ? $formatted['name'] : $formatted['sku'];
                $urlKeyString = (!empty($this->otherSettings['urlKeyPrefix']) ? $this->otherSettings['urlKeyPrefix'] : '') . $urlKeyString;
            } elseif (empty($item['parent'])) {
                $urlKeyString = !empty($formatted['name']) ? $formatted['name'] : $formatted['sku'];
            } elseif ($item['type_id'] == 'variant') {
                $urlKeyString = $formatted['sku'];
            }
            if ($urlKeyString) {
                $formatted['custom_attributes'][] = [
                    'attribute_code' => 'url_key',
                    'value'         =>  $this->connectorService->formatUrlKey($urlKeyString),
                ];
            }
        } elseif ($urlKey = $formatted[$attributeMappings['url_key']] ?? null) {
            $formatted['custom_attributes'][] = [
                    'attribute_code' => 'url_key',
                    'value'         =>  $this->connectorService->formatUrlKey($urlKey),
                ];
        }

        /** Support for Tier Prices Start*/
        if (isset($item['tier_prices'])) {
            $formatted['tier_prices'] = $item['tier_prices'];
        }
        /** Support for Tier Prices End*/

        /** Support for Downloadable Product Start*/
        if ($item['type_id'] == 'downloadable') {
            if (isset($item['links_purchased_separately'])) {
                $formatted['custom_attributes'][] = [
                    'attribute_code' => 'links_purchased_separately',
                    'value'         =>  $item['links_purchased_separately'],
                ];
            }
            if (isset($item['links_title'])) {
                $formatted['custom_attributes'][] = [
                    'attribute_code' => 'links_title',
                    'value'         =>  $item['links_title'],
                ];
            }
            if (isset($item['samples_title'])) {
                $formatted['custom_attributes'][] = [
                    'attribute_code' => 'samples_title',
                    'value'         =>  $item['samples_title'],
                ];
            }
            
            if (!isset($formatted['extension_attributes'])) {
                $formatted['extension_attributes'] = [];
            }
            $normalizedOptions = [];
            if (isset($item['downloadable_product_links'])) {
                $normalizedOptions = $this->formatDownloadableProductLinks($item);
            }

            $formatted['extension_attributes']['downloadable_product_links'] = $normalizedOptions;
            $normalizedSampleOptions = [];
            if (isset($item['downloadable_product_samples'])) {
                $normalizedSampleOptions = $this->formatDownloadableProductSamples($item);
            }

            $formatted['extension_attributes']['downloadable_product_samples'] = $normalizedSampleOptions;
        }

        /** Support for Downloadable Product End*/

        /** Support for Bundle Product Start*/

        if ($item['type_id'] === 'bundle' && isset($item['metadata']['bundle_product_options'])) {
            if (!isset($formatted['extension_attributes'])) {
                $formatted['extension_attributes'] = [];
            }

            $this->formatMagentoBundleCustomAttr($item['metadata']['bundle_product_options'], $formatted, $item['metadata']['identifier']);
        }
    
        
        /** Support for Bundle Product End*/
        $formatted = $this->sortFormatedDataIndexes($formatted);
        
        return $formatted;
    }

    private function formatMagentoBundleCustomAttr($data, &$formatted, $identifier)
    {
        foreach ($this->bundleCustomAttr as $key => $value) {
            if (isset($data[$key])) {
                if ($key == 'shipment_type') {
                    $formatted['custom_attributes'][] = [
                        'attribute_code' => $value,
                        'value'          =>  $data[$key] == 'separately' ? 1 : 0,
                     ];
                } elseif ($key == 'bundle_price_view') {
                    $formatted['custom_attributes'][] = [
                        'attribute_code' => $value,
                        'value'          =>  $data[$key] == 'Price Range' ? 0 : 1,
                    ];
                } else {
                    $formatted['custom_attributes'][] = [
                        'attribute_code' => $value,
                        'value'          =>  $data[$key] == true ? 0 : 1,
                    ];
                }
                unset($data[$key]);
            }
        }
        $optionId = 1;
        $id = 1;
        $formatted['extension_attributes']['bundle_product_options'] = [];
        foreach ($data as $key => $value) {
            # code...
            $productLink = [];
            if (isset($value['products']) && !empty($value['products'])) {
                $position = 1;
                foreach ($value['products'] as $productKey => $productValue) {
                    $productLink[] = [
                        "id" => $id,
                        "sku" => isset($productValue['sku']) ? $productValue['sku'] : '',
                        "option_id" => $optionId,
                        "qty" => isset($productValue['qty']) ? $productValue['qty'] : 1,
                        "position" => $position,
                        "is_default" => isset($productValue['is_default']) && $productValue['is_default'] == true ? true : false,
                        "can_change_quantity" => isset($productValue['can_change_quantity']) &&  $productValue['can_change_quantity'] == true ? 1 : 0,
                        "price" => 0.0
                    ];
                    $id++;
                    $position++;
                }
            }

            if (!empty($productLink)) {
                $formatted['extension_attributes']['bundle_product_options'][] = [
                    "option_id" => $optionId,
                    "title" => isset($value['title']) ? $value['title'] : '',
                    "required" => isset($value['required']) ? $value['required'] : false,
                    "type" => isset($value['type']) ? $value['type'] : 'select',
                    "position" => $optionId,
                    "sku" => $identifier,
                    "product_links" => $productLink,
                ];
                $optionId++;
            }
        }
    }

    private function formatDownloadableProductSamples($item)
    {
        $normalizedSampleOptions = [];
        $id = 1;
        foreach ($item['downloadable_product_samples'] as $value) {
            $normalizedSampleOptions[] = [
                'title' => $value['title'],
                'sort_order' => $id,
                'sample_type' => 'url',
                // $value['sample_type'] == 0 ? 'url' : 'file',
                'sample_url' => $value['sample_type'] == 0 ? $value['sample_url'] : (isset($value['sample_file']) && isset($value['sample_file']['filePath']) ? $this->connectorService->downloadableFileUrl($value['sample_file']['filePath']) : ''),
                'sample_file' => '',
                // isset($value['sample_file']) && isset($value['sample_file']['filePath']) ? $this->connectorService->downloadableFileUrl($value['sample_file']['filePath']) : '',
            ];

            $id++;
        }
        return $normalizedSampleOptions;
    }

    private function formatDownloadableProductLinks($item)
    {
        $normalizedOptions = [];
        $id = 1;
        foreach ($item['downloadable_product_links'] as $value) {
            $normalizedOptions[] = [
                'title' => $value['title'],
                'sort_order' => $id,
                'is_shareable' => $value['is_shareable'],
                'price' => $value['price'] ? $value['price'] : 0,
                'number_of_downloads' => $value['number_of_downloads'] ? $value['number_of_downloads'] : 0,
                'link_type' => 'url',
                // $value['link_type'] == 0 ? 'url' : 'file',
                'link_file' => '',
                //  isset($value['link_file']) && isset($value['link_file']['filePath']) ? $this->connectorService->downloadableFileUrl($value['link_file']['filePath']) : '',
                'link_url' => $value['link_type'] == 0 ? $value['link_url'] : (isset($value['link_file']) && isset($value['link_file']['filePath']) ? $this->connectorService->downloadableFileUrl($value['link_file']['filePath']) : ''),
                'sample_type' =>'url',
                //  $value['sample_type'] == 0 ? 'url' : 'file',
                'sample_url' => $value['sample_type'] == 0 ? $value['sample_url'] : (isset($value['sample_file']) && isset($value['sample_file']['filePath']) ? $this->connectorService->downloadableFileUrl($value['sample_file']['filePath']) : ''),
                'sample_file' => '',
                // isset($value['sample_file']) && isset($value['sample_file']['filePath']) ? $this->connectorService->downloadableFileUrl($value['sample_file']['filePath']) : '',
            ];

            $id++;
        }
        return $normalizedOptions;
    }

    /**
     * It sort the formated index data according to the magento API data
     * @var array $formatted
     *
     */
    protected function sortFormatedDataIndexes(array $formatted)
    {
        $sortedFormatted = [];
        $sortedIndexs = ["id", "sku", "name", "attribute_set_id", "price", "status", "visibility", "type_id", "created_at", "updated_at", "weight", "extension_attributes", "product_links", "options", "media_gallery_entries", "tier_prices", "custom_attributes"];

        foreach ($sortedIndexs as $index) {
            if (isset($formatted[$index])) {
                if (is_array($formatted[$index])) {
                    $sortedFormatted[$index] = [];
                }
                $sortedFormatted[$index] = $formatted[$index];
            }
        }

        $sortedFormatted = array_merge($sortedFormatted, array_diff_key($formatted, $sortedFormatted));
        
        /** Support for Bundle Discount Start*/
        if (isset($item[PropertiesNormalizer::BUNDLE_PRODUCT_OPTIONS])) {
            $this->bundleDiscount[PropertiesNormalizer::BUNDLE_PRODUCT_OPTIONS] = $item[PropertiesNormalizer::BUNDLE_PRODUCT_OPTIONS];
            unset($item[PropertiesNormalizer::BUNDLE_PRODUCT_OPTIONS]);
        }
        /** Support for Bundle Discount End*/

        return $formatted;
    }

    protected function modifyOptionValues($data, array $attributeMappings)
    {
        foreach ($data as $index => $attr) {
            $realAttrCode = !empty($attributeMappings[$attr['attribute_code']]) ? $attributeMappings[$attr['attribute_code']] : $attr['attribute_code'];
            $attribute = $this->attributeRepo->findOneByIdentifier($realAttrCode);
            if (empty($attribute)) {
                continue;
            }

            if ('pim_catalog_date' == $attribute->getType()) {
                $data[$index]['value'] = $this->formatDate($attr['value']);
            } elseif (in_array($attribute->getType(), $this->simpleAttributeTypes)) {
            } elseif (in_array($attribute->getType(), $this->selectAttributeTypes)) {
                if (is_array($attr['value'])) {
                    $data[$index]['value'] = [];
                    $attributeOptionValue = $this->magentoVersion < "2.3" ? [] : '';
                    foreach ($attr['value'] as $singleValue) {
                        $attributeOption = $this->getMappingByCode($singleValue. '(' . $attribute->getCode() . ')', 'option');
                        if ($attributeOption) {
                            if ($this->magentoVersion < "2.3") {
                                $attributeOptionValue[] = $attributeOption->getExternalId();
                            } else {
                                $attributeOptionValue .= ',' . $attributeOption->getExternalId();
                            }
                        }
                    }
                    $data[$index]['value'] = is_array($attributeOptionValue) ? $attributeOptionValue : trim($attributeOptionValue, ',');
                } elseif (gettype($attr['value']) == 'string') {
                    $attributeOption = $this->getMappingByCode($attr['value']. '(' . $attribute->getCode() . ')', 'option');
                    if ($attributeOption) {
                        $data[$index]['value'] = $attributeOption->getExternalId();
                    } else {
                        $data[$index]['value'] = 0;
                        // unset($data[$index]);
                    }
                }
            } elseif ("pim_catalog_metric" == $attribute->getType() && isset($this->otherSettings['metric_is_active']) && filter_var($this->otherSettings['metric_is_active'], FILTER_VALIDATE_BOOLEAN)) {
                if (in_array($data[$index]['attribute_code'], array_keys($this->connectorService->attributesAxesOptions()))) {
                    $attributeOption = $this->getMappingByCode(($data[$index]['value']). '(' . $data[$index]['attribute_code'] . ')', 'option');
                    $value = $data[$index]['value'] ?? 0;
                    if (null !== $attributeOption) {
                        $value = $attributeOption->getExternalId();
                    }

                    $data[$index] = [
                            'attribute_code' =>  $attr['attribute_code'],
                            'value' =>  $value
                        ];
                }
            }

            if (!empty($data[$index])) {
                $data[$index]['attribute_code'] = strtolower($data[$index]['attribute_code']);
            }
        }
        return $data;
    }

    protected function getcategoryIdsFromCategories($categories)
    {
        $catIds = [];
        foreach ($categories as $categoryCode) {
            $mapping = $this->getMappingByCode($categoryCode, 'category');
            if ($mapping && $mapping->getExternalId()) {
                $catIds[] = $mapping->getExternalId() ;
            }
        }

        return $catIds;
    }
    
    protected function changeAttributeMappingsBasedOnProductType($type)
    {
        $attributeMappings = $this->attributeMappings;
        if ($type == 'variant') {
            $attributeMappings = $this->connectorService->getSettings('magento2_child_attribute_mapping');
            $attributeMappings = array_merge(array_diff_key($this->attributeMappings, $attributeMappings), $attributeMappings);
        }

        return $attributeMappings;
    }

    protected function linkConfigurableChildToParent($childSku, $parentSku)
    {
        if (!empty($childSku) && !empty($parentSku)) {
            $url = $this->oauthClient->getApiUrlByEndpoint('addChild');
            $url = str_replace('{sku}', urlencode($parentSku), $url);

            $postData = [
                'childSku' => $childSku
            ];
            
            try {
                $lastResponse = $this->oauthClient->fetch($url, json_encode($postData), 'POST', $this->jsonHeaders);
            } catch (\Exception $e) {
                $lastResponse = json_decode($this->oauthClient->getLastResponse(), true);
                $message = (!empty($lastResponse['error']['message']) ? $lastResponse['error']['message'] : $lastResponse['message']);
                if (strpos($message, '"%1" and "%2"') == true) {
                    $message = str_replace('"%1" and "%2"', '"Variant 1" and "Variant 2"', $message);
                }

                if (!empty($lastResponse['error']['message']) || !empty($lastResponse['message'] && !in_array($lastResponse['message'], $this->linkSkuGeneralMsgs))) {
                    $this->stepExecution->addWarning(
                        'link sku error:' .
                        $message,
                        ['error' => true ],
                        new \DataInvalidItem([
                            'sku' => $childSku,
                            'Request URL' => $url,
                            'Request Data' => json_encode($postData),
                            'debugLine' => __LINE__
                            ])
                    );
                }
            }
        }
    }

    protected function formatValueForMagento($value, $channel, $locale, $storeViewCurrency, $index = null)
    {
        if (is_array($value)) {
            foreach ($value as $key => $aValue) {
                if (is_array($aValue)) {
                    if ($this->stepExecution->getJobExecution()->getJobInstance()->getJobName() == 'magento2_quick_export') {
                        $newValue = $aValue['data'];
                        break;
                    }

                    if (!isset($aValue['scope']) && !isset($aValue['locale'])) {
                        $newValue = $aValue['data'];
                        break;
                    } elseif (!isset($aValue['scope']) && isset($aValue['locale'])) {
                        if ($aValue['locale'] == $locale) {
                            $newValue = $aValue['data'];
                            break;
                        }
                    } elseif (isset($aValue['scope']) && !isset($aValue['locale'])) {
                        if ($aValue['scope'] == $channel) {
                            $newValue = $aValue['data'];
                            break;
                        }
                    } elseif (isset($aValue['scope']) && isset($aValue['locale'])) {
                        if ($aValue['scope'] == $channel && $aValue['locale'] == $locale) {
                            $newValue = $aValue['data'];
                            break;
                        }
                    }
                } else {
                    break;
                }
            }
        } else {
            $newValue = $value;
        }
        
        $value = isset($newValue) ? $newValue : null;
        if ($value && is_array($value)) {
            /* price */
            foreach ($value as $key => $aValue) {
                if (is_array($aValue)) {
                    if (array_key_exists('currency', $aValue)) {
                        if (!$aValue['currency'] || $aValue['currency'] == $storeViewCurrency) {
                            $value = !empty($aValue['amount']) ? (float)$aValue['amount'] : null;
                            break;
                        }
                        if ($key == count($value)-1) {
                            $value = !empty($value[0]['amount']) ? (float)$value[0]['amount'] : null ;
                        }
                    }
                } else {
                    break;
                }
            }
            /* metric */
            if (is_array($value) && array_key_exists('unit', $value)) {
                $tmpValue = $value;
                $value = !empty($value['amount']) ? (float)$value['amount'] : null;
                if (!empty($this->otherSettings['metric_selection']) && $this->otherSettings['metric_selection'] == "true") {
                    $value = !empty($value) ? (string) $value . ' ' . $tmpValue['unit'] : $value;
                }
            }
        }
        if (null === $value) {
            $value = '';
        }
        
        return $value;
    }

    public function getAttributeSets()
    {
        if (!$this->attributeSet) {
            $url = $this->oauthClient->getApiUrlByEndpoint('attributeSets');
            $this->oauthClient->fetch($url, null, 'GET', $this->jsonHeaders);
            $this->attributeSet = json_decode($this->oauthClient->getLastResponse(), true);
        }

        return $this->attributeSet;
    }

    protected function getParentMappingBySku($sku)
    {
        $parentMapping = $this->getMappingByCode($sku, self::AKENEO_ENTITY_NAME);
        if ($parentMapping && $this->stepExecution->getJobExecution()->getId() !== $parentMapping->getJobInstanceId()) {
            $this->em->remove($parentMapping);
            $this->em->flush();
            $parentMapping = null;
        }

        return $parentMapping;
    }
    
    protected function addProduct($productData, $parent, $storeViewCode)
    {
        if (!empty($productData)) {
            $requestType = 'POST';
            // $this->changeImagesName($productData);
           
            $productData = $this->checkProductAndModifyData($productData, $storeViewCode, $parent);
            
            $productAddUrl = $this->oauthClient->getApiUrlByEndpoint(self::AKENEO_ENTITY_NAME, $storeViewCode);
            if (isset($productData[self::AKENEO_ENTITY_NAME]['sku'])) {
                $productAddUrl = str_replace('{sku}', urlencode($productData[self::AKENEO_ENTITY_NAME]['sku']), $productAddUrl);
                $requestType = 'PUT';
            } else {
                $productAddUrl = str_replace('/{sku}', '', $productAddUrl);
            }

            try {
                $this->oauthClient->fetch($productAddUrl, is_array($productData) ? json_encode($productData) : $productData, $requestType, $this->jsonHeaders);
                /* log success */
                return json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
                /* log error */
                $lastResponse = json_decode($this->oauthClient->getLastResponse(), true);
                $sendData = $productData;
                if (!empty($sendData['product']['media_gallery_entries'])) {
                    unset($sendData['product']['media_gallery_entries']);
                }
                $message = !empty($lastResponse['message']) ? $lastResponse['message'] : $this->oauthClient->getLastResponse();
                if (strpos($message, '"%1" and "%2"') == true) {
                    $message = str_replace('"%1" and "%2"', '"Variant 1" and "Variant 2"', $message);
                }
                if (strpos($message, "The product that was requested doesn") !== false) {
                    $message = "Selected Products In bundle Product are not exported to magento";
                }
                $this->stepExecution->addWarning(
                    $message,
                    [],
                    new \DataInvalidItem(
                        [
                        'sku' => !empty($productData[self::AKENEO_ENTITY_NAME]['sku']) ? $productData[self::AKENEO_ENTITY_NAME]['sku'] : 'sku not found',
                        'debugLine' => __LINE__,
                        'requestData' => $sendData,
                        'responseData' => $this->oauthClient->getLastResponse(), true,
                    ]
                    )
                );

                return ['error' => json_decode($this->oauthClient->getLastResponse(), true)];
            }
        }
    }

    protected function updateProduct($product, $storeCode = 'all')
    {
        if ($product && empty($product['error'])) {
            /* fetch product */
            $productData = [
                self::AKENEO_ENTITY_NAME => [ 'custom_attributes' => [] ]
            ];

            $customAttributes = [];
            foreach ($product['custom_attributes'] as $attribute) {
                if (in_array($attribute['attribute_code'], ['image', 'thumbnail', 'small_image', 'url_key']) && $attribute['value'] !== "no_selection") {
                    $customAttributes[] = $attribute;
                }
            }

            $productData[self::AKENEO_ENTITY_NAME]['custom_attributes'] = $customAttributes;
            if (!empty($productData) && !empty($customAttributes)) {
                $url = $this->oauthClient->getApiUrlByEndpoint('getProduct', $storeCode);
                $url = str_replace('{sku}', urlencode($product['sku']), $url);
                try {
                    $this->oauthClient->fetch($url, is_array($productData) ? json_encode($productData) : $productData, 'PUT', $this->jsonHeaders);
                    
                    return json_decode($this->oauthClient->getLastResponse(), true);
                } catch (\Exception $e) {
                    /* log error */
                    $this->stepExecution->addWarning($this->oauthClient->getLastResponse(), ['error' => true ], new \DataInvalidItem([
                        'sku' => !empty($productData[self::AKENEO_ENTITY_NAME]['sku']) ? $productData[self::AKENEO_ENTITY_NAME]['sku'] : 'sku not found',
                        'debugLine' => __LINE__
                    ]));

                    return ['error' => json_decode($this->oauthClient->getLastResponse(), true)];
                }
            }
        }
    }

    protected function checkProductAndModifyData($productData, $storeViewCode, $parent = null)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getProduct', $storeViewCode);
        $url = str_replace('{sku}', urlencode($productData[self::AKENEO_ENTITY_NAME]['sku']), $url);
        
        /** Tier prices only send to the all store view, rest will be removed */
        if ($storeViewCode !== 'all' && isset($productData[self::AKENEO_ENTITY_NAME]['tier_prices'])) {
            unset($productData[self::AKENEO_ENTITY_NAME]['tier_prices']);
        }
        /** Downloadable Options only send to the all store view, rest will be removed */
        if ($storeViewCode !== 'all' && isset($productData['product']['extension_attributes']['downloadable_product_links'])) {
            unset($productData['product']['extension_attributes']['downloadable_product_links']);
        }
        if ($storeViewCode !== 'all' && isset($productData['product']['extension_attributes']['downloadable_product_samples'])) {
            unset($productData['product']['extension_attributes']['downloadable_product_samples']);
        }

        /* fetch product */
        try {
            $this->oauthClient->fetch($url, null, 'GET', $this->jsonHeaders);
            $response = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $response = [];
        }

        if (isset($productData[self::AKENEO_ENTITY_NAME]['type_id']) && $productData[self::AKENEO_ENTITY_NAME]['type_id'] === 'configurable' && !empty($response['extension_attributes']['configurable_product_links'])) {
            if (empty($productData[self::AKENEO_ENTITY_NAME]['extension_attributes'])) {
                $productData[self::AKENEO_ENTITY_NAME]['extension_attributes'] = [];
            }
            $productData[self::AKENEO_ENTITY_NAME]['extension_attributes']['configurable_product_links'] =  $response['extension_attributes']['configurable_product_links'];
        }

        $existingImages = [];
        $existingImageRoles = [];
        $existingImagesData = [];
        $currentImageRoles = [];
        if ($parent) {
            $this->getProductImageRoles($productData, $currentImageRoles, 'child_image_roles');
        } else {
            $this->getProductImageRoles($productData, $currentImageRoles, 'image_roles');
        }
        if (!empty($response['media_gallery_entries'])) {
            if (empty($productData[self::AKENEO_ENTITY_NAME]['custom_attributes'])) {
                $productData[self::AKENEO_ENTITY_NAME]['custom_attributes'] = [];
            }
            foreach ($response['media_gallery_entries'] as $image) {
                if (isset($image['media_type']) && isset($image['id']) && $image['media_type'] === 'external-video') {
                    if ($storeViewCode === 'all') {
                        $this->removeProductMedia($productData[self::AKENEO_ENTITY_NAME]['sku'], $image['id']);
                    }
                    continue;
                }

                if (isset($image['file']) && isset($image['id'])) {
                    $existingImages[$image['id']] = $image['file'];
                    $existingImagesData[$image['id']] = $image;
                    foreach ($currentImageRoles as $field => $fieldImage) {
                        if (in_array($field, $image['types']) && !in_array($field, array_keys($this->attributeMappings))) {
                            $existingImageRoles[$field] = $image['file'];
                        }
                        
                        if (preg_match('#' . $fieldImage . '#i', $image["file"]) !== 0  && !in_array($field, array_keys($this->attributeMappings))) {
                            $productData[self::AKENEO_ENTITY_NAME]['custom_attributes'][] = [
                                'attribute_code' => $field,
                                'value'         => $image["file"],
                            ];
                        }
                    }
                }
            }
        }

        if( @$mediaEntries = $productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries'] ) {
            foreach ($mediaEntries as $key => $mediaEntry) {
                if (isset($mediaEntry['media_type']) && $mediaEntry['media_type'] === 'external-video') {
                    if ($storeViewCode === 'all') {
                        $this->videoMediaEntries[$productData[self::AKENEO_ENTITY_NAME]['sku']][] = $mediaEntry;
                    }
                    unset($mediaEntries[$key]);
                    unset($productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries'][$key]);
                    continue;
                }
            }
        }
        
        if (!empty($existingImages)) {
            $mediaEntryLabel = [];
            foreach ($mediaEntries as $key => $mediaEntry) {
                if (isset($mediaEntry['content']['name'])) {
                    $nameArray = explode('.', $mediaEntry['content']['name']);
                    $name = reset($nameArray);
                    $name = substr($name, strpos($name, '_')+1);
                    $matchImages = preg_grep('#' . $name . '#i', $existingImages);
                    $mediaEntry['label'] = $mediaEntry['label'];
                    $mediaEntry['position'] = $mediaEntry['position'];
                    
                    if (count($matchImages)) {
                        unset($mediaEntries[$key]);
                        $outhData = [
                            'sku' => $productData[self::AKENEO_ENTITY_NAME]['sku'],
                            'storeViewCode' => $storeViewCode
                        ];
                        // $this->updateMatchedMediaAlts($mediaEntryLabel, $matchImages, $existingImagesData, $outhData);
                    }

                    foreach ($matchImages as $id => $image) {
                        unset($existingImages[$id]);
                    }
                }
            }

            foreach ($existingImages as $id => $image) {
                // checkRoles
                $nameArray = explode('.', $image);
                $name = reset($nameArray);
                $name = substr($name, strpos($name, '_')+1);
                $exitingRoles = preg_grep('#' . $name . '#i', $existingImageRoles);
                
                if ($exitingRoles) {
                    $responseImagesData = $response['media_gallery_entries'] ?? [];
                    
                    $updateProductData[self::AKENEO_ENTITY_NAME] = [];
                    foreach ($responseImagesData as $index => $imageData) {
                        foreach ($currentImageRoles as $field => $fieldImage) {
                            if (preg_match('#' . $fieldImage . '#i', $imageData["file"]) !== 0  && !in_array($field, array_keys($this->attributeMappings))) {
                                $updateProductData[self::AKENEO_ENTITY_NAME]['custom_attributes'][] = [
                                    'attribute_code' => $field,
                                    'value'         => $imageData["file"],
                                ];
                            }
                        }
                    }

                    $url = $this->oauthClient->getApiUrlByEndpoint('getProduct', $storeViewCode);
                    $url = str_replace('{sku}', urlencode($productData[self::AKENEO_ENTITY_NAME]['sku']), $url);
                    
                    /* update product image */
                    try {
                        $this->oauthClient->fetch($url, $updateProductData, 'PUT', $this->jsonHeaders);
                        $response = json_decode($this->oauthClient->getLastResponse(), true);
                    } catch (\Exception $e) {
                        $response = [];
                    }
                }

                // $response = $this->removeProductMedia($productData[self::AKENEO_ENTITY_NAME]['sku'], $id);
            }
            
            $mediaEntries = array_values($mediaEntries);
            if (empty($mediaEntries)) {
                unset($productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries']);
            } else {
                if (count($mediaEntries) === count($productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries'])) {
                    $productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries'] = $mediaEntries;
                }
            }
        }

        // Unset the main_image_data
        if (isset($productData[self::AKENEO_ENTITY_NAME]['main_image_data'])) {
            unset($productData[self::AKENEO_ENTITY_NAME]['main_image_data']);
        }
        
        return $productData;
    }
    
    public function updateMatchedMediaAlts($mediaEntry, $matchImages, $existingImagesData, $outhData = [])
    {
        foreach ($matchImages as $id => $value) {
            if ($existingImagesData[$id]['label'] != $mediaEntry['label']) {
                $updateMediaData = [];
                $updateMediaData['entry'] = $existingImagesData[$id];
                unset($updateMediaData['entry']['file']);
                unset($updateMediaData['entry']['types']);
                unset($updateMediaData['entry']['disabled']);

                $updateMediaData['entry']['label'] = $mediaEntry['label'];
                $updateMediaData['entry']['position'] = $mediaEntry['position'];
                
                $url = $this->oauthClient->getApiUrlByEndpoint('updateProductMedia', $outhData['storeViewCode']);
                $url = str_replace('{sku}', urlencode($outhData['sku']), $url);
                $url = str_replace('{entryId}', urlencode($id), $url);
                try {
                    $this->oauthClient->fetch($url, $updateMediaData, 'PUT', $this->jsonHeaders);
                    $response = json_decode($this->oauthClient->getLastResponse(), true);
                } catch (\Exception $e) {
                    $response = [];
                }
            }
        }
    }

    protected function removeProductMedia($productSKU, $mediaId)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('removeProductMedia');
        $url = str_replace('{sku}', urlencode($productSKU), $url);
        $url = str_replace('{entryId}', urlencode($mediaId), $url);
        /* delete extra images */
        
        try {
            $this->oauthClient->fetch($url, null, 'DELETE', $this->jsonHeaders);
            $response = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $response = [];
        }

        return $response;
    }

    protected function addProductMedias(array $groupMedias)
    {
        foreach ($groupMedias as $productSKU => $medias) {
            foreach ($medias as $media) {
                $storeId = 0;
                $storeCode = 'all';
                $mediaId = null;
                $idDefaultStoreView = false;
                $mediaStoreViewLocale = $media['store_view_locale'];
                if (
                    isset($this->getStoreMapping()[$this->defaultStoreViewCode]['locale'])
                    && $this->getStoreMapping()[$this->defaultStoreViewCode]['locale'] === $mediaStoreViewLocale
                ) {
                    $idDefaultStoreView = true;
                    $media['disabled'] = false;
                }

                if (null === $mediaStoreViewLocale) {
                    $media['disabled'] = false;
                }
    
                unset($media['store_view_locale']);
                $mediaId = $this->addProductMedia($media, $productSKU, $storeCode, $storeId);
                if (!empty($mediaId) && !is_array($mediaId)) {
                    foreach ($this->getStoreMapping() as $storeCode => $storeMapping) {
                        unset($media['content']);
                        if ((!empty($mediaStoreViewLocale)
                            && !empty($storeMapping['locale'])
                            && $storeMapping['locale'] == $mediaStoreViewLocale)
                            || $idDefaultStoreView
                        ) {
                            $storeId = $storeMapping['id'];
                            $media['disabled'] = false;
                            $media['id'] = $mediaId;
                            if ($idDefaultStoreView) {
                                $media['disabled'] = true;
                                if ($storeMapping['locale'] == $mediaStoreViewLocale) {
                                    $media['disabled'] = false;
                                }
                            }
                            
                            $this->addProductMedia($media, $productSKU, $storeCode, $storeId);
                            if (
                                isset($this->getStoreMapping()[$this->defaultStoreViewCode]['locale'])
                                && $this->getStoreMapping()[$this->defaultStoreViewCode]['locale'] === $mediaStoreViewLocale
                            ) {
                                $storeId = $storeMapping['id'];
                                $media['disabled'] = false;
                                $media['id'] = $mediaId;
                                $storeCode = 'all';
                                $this->addProductMedia($media, $productSKU, $storeCode, $storeId);
                            }
                        }
                    }
                }
            }
        }
    }

    protected function checkVideoInStore($storeLocale)
    {
        $sMappingData['disabled'] = true;
        $sMappingData['store_id'] = 0;
        $sMappingData['storeCode'] = 'all';
        foreach ($this->getStoreMapping() as $storeCode => $storeMapping) {
            if (!empty($storeLocale)) {
                if (!empty($storeMapping['locale']) && $storeMapping['locale'] == $storeLocale) {
                    $sMappingData['disabled'] = false;
                    $sMappingData['store_id'] = $storeMapping['id'];
                    $sMappingData['storeCode'] = $storeCode;
                    break;
                }
            }
        }

        return $sMappingData;
    }
    protected function addProductMedia($media, $productSKU, $storeCode, $storeId)
    {
        unset($media['store_view_locale']);
        $url = $this->oauthClient->getApiUrlByEndpoint('addProductMedia', $storeCode);
        $url = str_replace('{sku}', urlencode($productSKU), $url);
        if (isset($media['id'])) {
            $method = 'PUT';
            $url = str_replace('{entryId}', urlencode($media['id']), $url);
        } else {
            $method = 'POST';
            $url = str_replace('{entryId}', '', $url);
        }

        if (isset($media['position'])) {
            unset($media['position']);
        }

        try {
            $this->oauthClient->fetch($url, json_encode(["store_id" => $storeId, "entry" => $media]), $method, $this->jsonHeaders);
            $response = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $response = ['message'=>$e->getMessage()];
        }

        return $response;
    }

    protected function getStoreViewIdByLocaleCode($localeCode)
    {
        $storeId = null;

        foreach ($this->getStoreMapping() as $storeMapping) {
            if (!empty($storeMapping['locale']) && $storeMapping['locale'] == $localeCode) {
                $storeId = $storeMapping['id'];
                break;
            }
        }

        return $storeId;
    }

    protected $lastIncrementSku;
    protected function quickExportIncrementById($sku)
    {
        $params = $this->getParameters();
        $isQuickExport = !empty($params['filters']['0']['context']);

        if ($isQuickExport && $sku !== $this->lastIncrementSku) {
            $this->lastIncrementSku = $sku;
            $this->stepExecution->incrementSummaryInfo('write');
        }
    }

    protected function getBooleanAttributes()
    {
        if (!$this->booleanAttributes && gettype($this->booleanAttributes) !== 'array') {
            $this->booleanAttributes = $this->attributeRepo->getAttributeCodesByType(
                'pim_catalog_boolean'
            );
        }

        return $this->booleanAttributes;
    }

    protected function typeCastValue($code, $value)
    {
        if (in_array($code, array_keys($this->strictTypes))) {
            switch ($this->strictTypes[$code]) {
                case 'string':
                    $value = (string)$value;
                    break;
                case 'integer':
                case 'int':
                    $value = (int)$value;
                    break;
            }
        }

        return $value;
    }

    protected function getOptionsByAttributeCode($code)
    {
        $mappings = $this->mappingRepository->getOptionsByAttributeCodeAndApiUrl($code, $this->getApiUrl());
        
        return $mappings;
    }

    protected function formatDate($date)
    {
        $dateObj = new \DateTime($date);

        return $dateObj->format('Y-m-d H:i:s');
    }

    protected function updateStoreMappingValueByLocalesChannels(array $storeMappings, array $locales, array $channels)
    {
        foreach ($storeMappings as $key => $storeMapping) {
            if (empty($storeMapping['locale'])
                || empty($storeMapping['channel'])
                || !in_array($storeMapping['locale'], $locales)
                || !in_array($storeMapping['channel'], $channels)
            ) {
                unset($storeMappings[$key]);
            }
        }
       
        return $storeMappings;
    }

    /**
    * @param array $product
    */
    protected function changeImagesName(&$product)
    {
        if (!empty($product[self::AKENEO_ENTITY_NAME]['media_gallery_entries'])) {
            foreach ($product[self::AKENEO_ENTITY_NAME]['media_gallery_entries'] as $imageDataKey => $imageData) {
                if (isset($imageData['content']) && isset($imageData['content']['name'])) {
                    $product[self::AKENEO_ENTITY_NAME]['media_gallery_entries'][$imageDataKey]['content']['name'] = uniqid().substr($imageData['content']['name'], strpos($imageData['content']['name'], '_'));
                }
            }
        }
        
        if (!empty($product[self::AKENEO_ENTITY_NAME]['main_image_data'])) {
            $product[self::AKENEO_ENTITY_NAME]['main_image_data'] = uniqid().substr($product[self::AKENEO_ENTITY_NAME]['main_image_data'], strpos($product[self::AKENEO_ENTITY_NAME]['main_image_data'], '_'));
        }
    }

    /** It update the step execution
     *
     * @param \StepExecution $stepExecution
     */
    public function updateStepExecution(\StepExecution $stepExecution)
    {
        $this->jobRepository->updateStepExecution($stepExecution);
    }

    
    protected $simpleAttributeTypes = ['pim_catalog_text', 'pim_catalog_number','pim_catalog_textarea','pim_catalog_date','pim_catalog_boolean'];

    protected $selectAttributeTypes = ['pim_catalog_multiselect', 'pim_catalog_simpleselect'];

    protected $strictTypes = [
        'description'       => 'string',
        'short_description' => 'string',
        'thumbnail_label'   => 'string',
        'small_image_label' => 'string',
        'image_label'       => 'string',
        'visibility'        => 'int',
        'status'            => 'int',
        'has_options'       => 'int',
        'tax_class_id'      => 'int',
    ];
    
    protected $linkSkuGeneralMsgs = [
        'Produkt wurde bereits angehängt',
        'Product has already been attached',
        'Product has been already attached',
        'Le produit a déjà été attaché',
        'Product is al bijgevoegd',
        'The product is already attached.',
        'Le produit est déjà attaché.',
        'Das Produkt ist bereits angehängt.',
        'Product is al toegevoegd',
        'Das Produkt ist bereits beigefügt',
        'Das Produkt ist bereits beigefügt.',
        'Het product is al gekoppeld.',
    ];

    protected $stockItemAttributes = [
        "quantity" => 'integer',
        "qty" => 'integer',
        "is_in_stock" => 'boolean',
        "quantity_and_stock_status" => 'boolean',
        "is_qty_decimal" => 'boolean',
        "use_config_min_qty" => 'integer',
        "min_qty" => 'integer',
        "use_config_min_sale_qty" => 'int',
        "min_sale_qty" => 'integer',
        "use_config_max_sale_qty" => 'boolean',
        "max_sale_qty" => 'integer',
        "use_config_backorders" => 'boolean',
        "backorders" => 'integer',
        "use_config_notify_stock_qty" => 'boolean',
        "notify_stock_qty" => 'integer',
        "use_config_qty_increments" => 'boolean',
        "qty_increments" => 'integer',
        "use_config_enable_qty_inc" => 'boolean',
        "enable_qty_increments" => 'boolean',
        "use_config_manage_stock" => 'boolean',
        "manage_stock" => 'boolean',
        "low_stock_date" => 'boolean',
        "is_decimal_divided" => 'boolean',
        "stock_status_changed_auto" => 'integer',
    ];

    protected $configSetting = [
        "min_qty" =>  "use_config_min_qty",
        "max_sale_qty" => "use_config_max_sale_qty",
        "min_sale_qty" => "use_config_min_sale_qty",
        "backorders" => "use_config_backorders",
        "notify_stock_qty" => "use_config_notify_stock_qty",
        "qty_increments" => "use_config_qty_increments",
        "enable_qty_increments" => "use_config_enable_qty_inc",
        "manage_stock" => "use_config_manage_stock",
    ];

    protected $bundleCustomAttr = [
        "shipment_type" =>  "shipment_type",
        "bundle_sku_type" => "sku_type",
        "bundle_price_type" => "price_type",
        "bundle_price_view" => "price_view",
        "bundle_weight_type" => "weight_type",
    ];

    protected function getProductImageRoles(&$productData, &$currentImageRoles, $imageRoleType)
    {
        if (isset($productData['product']['media_gallery_entries'][$imageRoleType])) {
            $imageRoles = $productData['product']['media_gallery_entries'][$imageRoleType];
            foreach ($imageRoles as $imageRoleKey => $imageRole) {
                $imageRoleKey = substr($imageRoleKey, strpos($imageRoleKey, '_')+1);
                foreach ($imageRole as $role) {
                    $currentImageRoles[$role] = $imageRoleKey;
                }
            }
            unset($productData['product']['media_gallery_entries'][$imageRoleType]);
        }
    }
}
