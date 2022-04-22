<?php

namespace Webkul\Magento2Bundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\Connector\Reader\Import\BaseReader;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * import attributes reader from Magento 2
 *
 * @author    webkul <support@webkul.com>
 * @copyright 2010-18 Webkul (http://store.webkul.com/license.html)
 */
class AttributeReader extends BaseReader implements \ItemReaderInterface, \StepExecutionAwareInterface, \InitializableInterface
{
    use DataMappingTrait;

    const AKENEO_ENTITY_NAME = 'attribute';

    const IMAGE_ATTRIBUTES_COUNT  = 20;

    const PAGE_SIZE = 50;

    protected $items;

    protected $locale;

    protected $jsonHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

    protected $itemIterator;

    private $firstRead;

    protected $currentPage;

    protected $totalAttrCount;

    protected $storeCode;

    protected $attributeCode = [];

    protected $storeMapping;

    protected $attributeCount = 0;

    protected $initRead = false;
   
    public function initialize()
    {
        if (!$this->initRead) {
            $credentials = $this->connectorService->getCredentials();
    
            if (!$this->oauthClient) {
                $this->oauthClient = new OAuthClient($credentials['authToken'], $credentials['hostName']);
            }
    
            $filters = $this->stepExecution->getJobParameters()->get('filters');
            $this->locales = !empty($filters['structure']['locales']) ? $filters['structure']['locales'] : [];
            if (!in_array($this->defaultLocale, $this->locales)) {
                $this->stepExecution->addWarning('Invalid Job', [], new \DataInvalidItem([$this->defaultLocale. ' default store view locale is not added in job']));
                $this->stepExecution->setTerminateOnly();
            } else {
                $this->storeMapping = $this->connectorService->getStoreMapping();
                foreach ($this->storeMapping as $storeCode => $storeData) {
                    if ($storeCode == 'allStoreView') {
                        $this->storeCode = 'all';
                        break;
                    }
                }
                $this->currentPage = 1;
                $attributes = $this->getAttributes($this->storeCode, $this->currentPage);
                $items = [];
            }
            
            if (!empty($attributes['items'])) {
                $items = $this->formatData($attributes['items']);
                $this->items = $items;
                
                $this->firstRead = false;
            }

            $this->initRead = true;
        }
    }

    protected $idata;
    /**
     * {@inheritdoc}
     */
    public function read()
    {
        if ($this->itemIterator === null && $this->firstRead === false) {
            $this->itemIterator = new \ArrayIterator($this->items);
            $this->firstRead = true;
        }

        $item = $this->itemIterator->current();
         
        while (
            null === $item
            && $this->totalAttrCount
            && ($this->currentPage * self::PAGE_SIZE <= $this->totalAttrCount
            || (($this->currentPage * self::PAGE_SIZE - $this->totalAttrCount)  <  self::PAGE_SIZE))
        ) {
            $this->currentPage++;
            $attributes = $this->getAttributes($this->storeCode, $this->currentPage);
            
            $items = [];
            if (!empty($attributes['items'])) {
                $items = $this->formatData($attributes['items']);
                 
                $this->itemIterator = new \ArrayIterator($items);
                $item = $this->itemIterator->current();
            }
            $this->currentPage++;
        }

        

        if ($item !== null) {
            $this->stepExecution->incrementSummaryInfo('read');
            $this->itemIterator->next();
        }
        
        return  $item;
    }

    protected function getAttributes($storeCode, $currentPage, $pageSize = self::PAGE_SIZE)
    {
        /* store-view wise and Page limit wise */
        $url = $this->oauthClient->getApiUrlByEndpoint('attributes', $storeCode);
        $url = strstr($url, '?', true) . '?searchCriteria[pageSize]='.$pageSize.'&searchCriteria[currentPage]=' . $currentPage;
        $method = 'GET';
        $attributes = [];
        
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            if (!empty($results['total_count'])
            && (
                $currentPage * $pageSize <= $results['total_count']
                ||
                (($currentPage * $pageSize - $results['total_count'])  <  $pageSize)
            )) {
                $this->totalAttrCount = $results['total_count'];

                    
                $attributes = $results;
            }
        } catch (\Exception $e) {
            $lastResponse = json_decode($this->oauthClient->getLastResponse(), true);
            $this->stepExecution->addWarning("Error! can't get attributes", [], new \DataInvalidItem([
                    "Response" => !empty($lastResponse['message']) ? $lastResponse['message'] : '',
                    "Request URL" => $url,
                    "Request Method" => $method,
                    "debug_Line" => __LINE__,
            ]));
        }
      
        return $attributes;
    }


    protected function formatData($attributes)
    {
        $results = [];
        // fetch attribute Mapping
        $attributeMappings = $this->connectorService->getAttributeMappings();
        $attributeMappings = !empty($attributeMappings) ? $attributeMappings : [];
        //fetch other Mapping
        $otherMapping = $this->connectorService->getOtherMappings();
        
        $finalOtherMapping = [];
        if (!empty($otherMapping['import_custom_fields'])) {
            foreach ($otherMapping['import_custom_fields'] as $key => $value) {
                $finalOtherMapping[$value] = $value;
            }
        }

        foreach ($attributes as $key => $attribute) {
            $attributeEntity = null;
            if (!$attribute['is_user_defined']) {
                if (in_array($attribute['attribute_code'], $this->skipAttributes)) {
                    continue;
                }
            }

            $code = $this->connectorService->matchAttributeCodeInDb($attribute['attribute_code']) ? : $attribute['attribute_code'];
            
            $type = array_key_exists($attribute['frontend_input'], $this->attributeTypes) ? $this->attributeTypes[$attribute['frontend_input']] : null;
            
            if (!empty($type)) {
                // if attribute present in mapping skipped
                $attributeMappings = array_change_key_case($attributeMappings);
                $code = strtolower($code);

                if (!array_key_exists($code, $attributeMappings)) {
                    if (in_array($code, $this->attributeMapped)) {
                        $attributeMappings[$code] = $code;
                        if (strcasecmp($code, 'qty') == 0) {
                            $attributeMappings['quantity'] = $code;
                        }
                        //save attribute mapping in db
                        $this->connectorService->saveAttributeMappings($attributeMappings);
                    } else {
                        if (!in_array($code, array_keys($finalOtherMapping))) {
                            $otherMapping['import_custom_fields'][] = $code;
                            $finalOtherMapping[$code] = $code;
                        }
                    }
                }

                if (in_array($code, $this->attributeCode)) {
                    continue;
                } else {
                    $this->attributeCode[] = $code;
                }

                $attributeEntity = $this->connectorService->getAttributeByCode($code);
                
                /* add attribute codes for attribute_option export */
                $this->addOptionAttributeCode($attributeEntity ? $attributeEntity->getType() : $type, $attribute['attribute_code']);

                $result = [
                    'code'                      => $attributeEntity ? $attributeEntity->getCode() : $code,
                    'localizable'               => $attributeEntity ? $attributeEntity->isLocalizable() : $attribute['scope'] === 'store',
                    'scopable'                  => $attributeEntity ? $attributeEntity->isScopable() : $attribute['scope'] !== 'global',
                    'sort_order'                => $attributeEntity ? $attributeEntity->getSortOrder() : $attribute['position'],
                    'type'                      => $attributeEntity ? $attributeEntity->getType() : $type,
                    'unique'                    => $attributeEntity ? $attributeEntity->isUnique() : (boolean)$attribute['is_unique'],
                    'useable_as_grid_filter'    => $attribute['is_filterable_in_grid'],
                    'wysiwyg_enabled'           => $attributeEntity ? $attributeEntity->isWysiwygEnabled() : ($type === 'pim_catalog_textarea' ? (boolean)$attribute['is_wysiwyg_enabled'] : null),
                    'group'                     => $attributeEntity && $attributeEntity->getGroup() ? $attributeEntity->getGroup()->getCode() : 'other',  // change this
                ];
                
                if (!strcasecmp($result['code'], 'sku')) {
                    continue;
                }

                if (!in_array($type, ["pim_catalog_text", "pim_catalog_number"]) && $result['unique']) {
                    $result['unique'] = false;
                }

                if ($type === "pim_catalog_price_collection") {
                    $result['decimals_allowed'] =  $attributeEntity ? $attributeEntity->isDecimalsAllowed() : true;
                }
                if ($type === "pim_catalog_metric") {
                    $result["metric_family"] = $attributeEntity ? $attributeEntity->getMetricFamily() : "Weight";
                    $result["default_metric_unit"] = $attributeEntity ? $attributeEntity->getDefaultMetricUnit() : "POUND";
                    $result["decimals_allowed"] = $attributeEntity ? $attributeEntity->isDecimalsAllowed() : true;
                    $result["negative_allowed"] = $attributeEntity ? $attributeEntity->isNegativeAllowed() : true;
                }
                
                foreach ($this->storeMapping as $storeCode => $storeData) {
                    if (!empty($storeData['locale'])) {
                        if ($result['code'] ==='quantity_and_stock_status') {
                            $result['labels'][$storeData['locale']] = $result['code'];
                            continue;
                        }
                        foreach ($attribute['frontend_labels'] as $labels) {
                            if ($labels['store_id'] == $storeData['id'] && $labels['label']) {
                                $result['labels'][$storeData['locale']] = $labels['label'];
                            }
                        }
                        if (empty($result['labels'][$storeData['locale']])) {
                            $result['labels'][$storeData['locale']] = !empty($attribute['default_frontend_label']) ? $attribute['default_frontend_label'] : $result['code'];
                        }
                    }
                }
            
                // Add to Mapping in Database
                $externalId = !empty($attribute['attribute_id']) ? $attribute['attribute_id'] : null;
                $relatedId = !empty($attribute['entity_type_id']) ? $attribute['entity_type_id'] : null;
                $code = !empty($result['code']) ? $result['code'] : null;
                if ($code && $externalId && $relatedId) {
                    $mapping = $this->addMappingByCode($code, $externalId, $relatedId, $this::AKENEO_ENTITY_NAME);
                }
                
                $results[] = $result;
            }
        }
        
        /* Image Type Attribute Added */
        $imageAttributes = $this->connectorService->getImageAttributeCodes();
        $imageCount = self::IMAGE_ATTRIBUTES_COUNT - count($imageAttributes);
        if ($imageCount && $imageCount > 0) {
            $counter = 1;
            while ($imageCount) {
                $imageName = 'image' . $counter++;
                if (!in_array($imageName, $imageAttributes)) {
                    $results[] = [
                        'code'                      => $imageName,
                        'localizable'               => false,
                        'scopable'                  => false,
                        'sort_order'                => 0,
                        'type'                      => 'pim_catalog_image',
                        'unique'                    => false,
                        'group'                     => 'other',
                        'labels'                    => [$this->defaultLocale => $imageName]
                    ];
                    $imageCount--;
                }
            }
        }
        $imageAttributes = $this->connectorService->getImageAttributeCodes();
        $otherMapping['images'] =  $imageAttributes;
        //$this->connectorService->saveOtherMappings($otherMapping);
        /* End Image Type Attribute Added */
        
        return $results;
    }

    protected function addOptionAttributeCode($type, $attributeCode)
    {
        /* add attribute codes for attribute_option export */
        if (in_array($type, ['pim_catalog_multiselect', 'pim_catalog_simpleselect'])) {
            $rawParams = $this->stepExecution->getJobExecution()->getJobInstance()->getRawParameters();
            if (empty($rawParams['selectTypeAttributes'])) {
                $rawParams['selectTypeAttributes'] = [];
            }
            $rawParams['selectTypeAttributes'][] = $attributeCode;
            $this->stepExecution->getJobExecution()->getJobInstance()->setRawParameters($rawParams);
            $rawParams = $this->stepExecution->getJobExecution()->getJobInstance()->getRawParameters();
        }
    }

    protected $attributeTypes = [
        'text' => 'pim_catalog_text',
        'textarea' => 'pim_catalog_textarea',
        'date' => 'pim_catalog_date',
        'boolean' => 'pim_catalog_boolean',
        'multiselect' => 'pim_catalog_multiselect',
        'select' => 'pim_catalog_simpleselect',
        'price' => 'pim_catalog_price_collection',
        'weight' => 'pim_catalog_metric',
    ];
    
    protected $attributeMapped = [
        'sku',
        'name',
        'weight',
        'price',
        'description',
        'short_description',
        'quantity',
        'meta_title',
        'meta_keyword',
        'meta_description',
        'url_key',
        'qty'
    ];

    protected $skipAttributes = [
         "image",
         "small_image",
         "thumbnail",
         "media_gallery",
         "old_id",
         "tier_price",
         "gallery",
         "url_path",
         "minimal_price",
         "is_recurring",
         "recurring_profile",
         "custom_design",
         "custom_design_from",
         "custom_design_to",
         "custom_layout_update",
         "page_layout",
         "category_ids",
         "options_container",
         "required_options",
         "has_options",
         "image_label",
         "small_image_label",
         "thumbnail_label",
         "created_at",
         "updated_at",
         "msrp_display_actual_price_type",
         "msrp",
         "price_type",
         "sku_type",
         "weight_type",
         "price_view",
         "shipment_type",
         "links_purchased_separately",
         "samples_title",
         "links_title",
         "links_exist",
         "allowed_to_quotemode",
         "fooman_product_surcharge",
         "custom_layout",
         "swatch_image",
         "custom_label"
        ];
}
