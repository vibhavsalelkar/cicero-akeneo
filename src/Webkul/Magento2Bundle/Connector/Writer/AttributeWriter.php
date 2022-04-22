<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$versionCompatibily = new AkeneoVersionsCompatibility();
$versionCompatibily->checkVersionAndCreateClassAliases();

/**
 * Add attributes to magento2 Api
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class AttributeWriter extends BaseWriter implements \ItemWriterInterface
{
    use DataMappingTrait;
    
    const AKENEO_ENTITY_NAME = 'attribute';
    const ERROR_ALREADY_EXIST = '%1 already exists.';

    protected $reservedKeys;
    protected $modifiedScope = false;
    protected $otherSettings;
    protected $channelRepository;

    public function __construct(\Doctrine\ORM\EntityManager $em, Magento2Connector $connectorService)
    {
        $this->em = $em;
        $this->connectorService = $connectorService;
    }

    /**
     * write attributes to magento2 Api
     */
    public function write(array $items)
    {
        // modify imageAttributeScopes
        $this->modifyImagesAttributeScope();
       
        $parameters = $this->getParameters();
        $storeMapping = $this->getStoreMapping();
        
        $storeViews = $this->getCredentials()['storeViews'] ?? [];
        if (!in_array($this->defaultLocale, $parameters['filters']['structure']['locales'])) {
            $this->stepExecution->addWarning('Invalid Job', [], new \DataInvalidItem([$this->defaultLocale. ' default store view locale is not added in job']));
            $this->stepExecution->setTerminateOnly();
        }
        
        $storeMappingIds = [];
        foreach ($storeViews as $storeView) {
            $storeMappingIds[$storeView['code']] = $storeView['id'];
        }
        
        if (!$this->reservedKeys) {
            $attributeMappings = $this->connectorService->getAttributeMappings();
            
            $this->reservedKeys = array_merge(
                array_unique(array_filter(array_values($attributeMappings))),
                array_keys($attributeMappings),
                $this->reservedAttributes
            );
        }
        if (!$this->otherSettings) {
            $this->otherSettings = $this->connectorService->getSettings();
        }

        while (count($items)) {
            $item = array_shift($items);
            $item['code'] = strtolower($item['code']);
            
            $errorMsg = false;
            $updateTrack = null;

            $locale = in_array($this->defaultLocale, array_keys($item['labels'])) ? $this->defaultLocale : (count($item['labels']) ? array_keys($item['labels'])[0] : 0);
            $name = !empty($item['labels'][$locale]) ? $item['labels'][$locale] : null;
            
            $this->storeViewCode = '';
            if (in_array($item['code'], $this->reservedKeys)) {
                $msg = $item['code'] . ' is reserved or mapped to reserved attributes';
                $this->stepExecution->addWarning($msg, [], new \DataInvalidItem([ 'code' => $item['code'] ]));
                $this->stepExecution->incrementSummaryInfo('skipped');
                continue;
            }

            /* validations */
            if (!$name) {
                $name = $item['code'];
            }

            if (!array_key_exists($item['type'], $this->attributeTypes)) {
                $msg = $item['type'] . ' attributes are not supported yet';
                $this->stepExecution->addWarning($msg, [], new \DataInvalidItem([ 'code' => $item['code'] ]));
                $this->stepExecution->incrementSummaryInfo('skipped');
                continue;
            }

            $item['name'] = $name;
            $isUsedAsAxis = false;
            // support for metric  type attributes as select type
            if (isset($this->otherSettings['metric_is_active']) && filter_var($this->otherSettings['metric_is_active'], FILTER_VALIDATE_BOOLEAN) && $item['type'] == 'pim_catalog_metric') {
                $isUsedAsAxis = $this->connectorService->checkAttrUsedAsAxis($item['code']);
                $item['type'] = $isUsedAsAxis ? 'select' : 'text';
            } else {
                $item['type'] = $this->attributeTypes[$item['type']];
            }

            if (isset($item['auto_option_visual_swatch']) && $item['auto_option_visual_swatch'] && $item['type'] === "select") {
                $item['type'] = 'visualswatch';
            }
            /* multi locale */
            $item['frontend_labels'] = [];
            foreach ($storeMappingIds as $storeViewCode => $storeGroupId) {
                $locale = !empty($storeMapping[$storeViewCode]['locale']) ? $storeMapping[$storeViewCode]['locale'] : 'NA';
                
                // if (!in_array($locale, $parameters['filters']['structure']['locales'])) {
                //     $locale = $this->defaultLocale;
                // }
                /* get storeViewId by code and then use in data */
                
                if ($storeGroupId && !empty($item['labels'][$locale]) && in_array($locale, $parameters['filters']['structure']['locales'])) {
                    $item['frontend_labels'][] = [
                        'store_id' => $storeGroupId,
                        'label'    => $item['labels'][$locale],
                    ];
                }
            }
            
            $mapping = $this->getMappingByCode($item['code'], self::AKENEO_ENTITY_NAME, false);
            $data = $this->createArrayFromDataAndMatcher($item, $this->matcher, SELF::AKENEO_ENTITY_NAME);

            if ($this->stepExecution->getJobParameters()->has('isFilterableInSearch')) {
                $data['attribute']['is_filterable'] = $this->stepExecution->getJobParameters()->get('isFilterableInSearch');
                $data['attribute']['is_filterable_in_search'] = $this->stepExecution->getJobParameters()->get('isFilterableInSearch');
            } else {
                $data['attribute']['is_filterable'] = false;
                $data['attribute']['is_filterable_in_search'] = false;
            }

            $data[self::AKENEO_ENTITY_NAME] = array_merge(
                $data[self::AKENEO_ENTITY_NAME],
                [
                        'is_html_allowed_on_front' => (boolean)$item['wysiwyg_enabled'],
                        'scope'                    => empty($item['localizable']) ? 'global' : 'store'
                    ]
            );
            
            if ($mapping) {
                $updateTrack = $this->connectorService->getEntityTrackByEntityAndCode(self::AKENEO_ENTITY_NAME, $item['code']);
                if (!empty($parameters['addNewOnly']) && !$updateTrack) {
                    $this->stepExecution->incrementSummaryInfo('already_exported');
                    continue;
                }

                /* update resource */
                if ($mapping->getExternalId()) {
                    $data[self::AKENEO_ENTITY_NAME]['attribute_id'] = $mapping->getExternalId();
                    $data[self::AKENEO_ENTITY_NAME]['entity_type_id'] = $mapping->getRelatedId();

                    $attribute = $this->addAttribute($data, $item['code']);

                    if (!empty($attribute['error']) && $attribute['code'] == Response::HTTP_NOT_FOUND) {
                        $mapping = $this->deleteMapping($mapping);
                        unset($data[self::AKENEO_ENTITY_NAME]['attribute_id']);
                    }
        
                    if (isset($this->otherSettings['metric_is_active']) && filter_var($this->otherSettings['metric_is_active'], FILTER_VALIDATE_BOOLEAN)) {
                        if (empty($attribute['error']) && $isUsedAsAxis && isset($attribute['frontend_input']) && $attribute['frontend_input'] == "text") {
                            $deleteResponse = $this->deleteAttribute($data, $item['code']);
                            if (empty($deleteResponse['error'])) {
                                unset($data[self::AKENEO_ENTITY_NAME]['attribute_id']);
                                unset($data[self::AKENEO_ENTITY_NAME]['entity_type_id']);
                                $this->addAttribute($data);
                            }
                        }
                    }

                    /** If attribute exist at magento then update the mapping */
                    if (isset($attribute['attribute_id']) && isset($attribute['entity_type_id'])) {
                        $this->addMappingByCode($item['code'], $attribute['attribute_id'], $attribute['entity_type_id']);
                    }
                }
                /* increment write count */
                if (!$errorMsg) {
                    $this->stepExecution->incrementSummaryInfo('update');
                }
            }
            if (!empty($updateTrack)) {
                $this->connectorService->removeTrack($updateTrack);
            }
            if (!$mapping) {
                $data[self::AKENEO_ENTITY_NAME] = array_merge(
                    $data[self::AKENEO_ENTITY_NAME]
                );
                /* add resource */
                $attribute = $this->addAttribute($data);
                if (!empty($attribute['error'])) {
                    /* already exist */
                    if (!empty($attribute['error']['message'])) {
                        $attribute = $this->getAttributeByCode($item['code']);
                        $errorMsg = true;
                        $this->stepExecution->addWarning('Skipping', [], new \DataInvalidItem([
                            'Attribute'=> $data,
                            'Error' => 'Attribute Code is reserved by the magento Or Attribute Already available at magento',
                        ]));

                        /* re request to update */
                        if (!empty($attribute['attribute_id'])) {
                            $data[self::AKENEO_ENTITY_NAME]['attribute_id'] = $attribute['attribute_id'];
                            $data[self::AKENEO_ENTITY_NAME]['entity_type_id'] = $attribute['entity_type_id'];
                            $data[self::AKENEO_ENTITY_NAME]['backend_type'] = $attribute['backend_type'];

                            $this->addAttribute($data, $item['code']);
                            $this->addMappingByCode($item['code'], $attribute['attribute_id'], $attribute['entity_type_id']);
                        }
                    }
                } elseif (!empty($attribute['attribute_id'])) {
                    $this->addMappingByCode(
                        $item['code'],
                        $attribute['attribute_id'],
                        !empty($attribute['entity_type_id']) ? $attribute['entity_type_id'] : null
                    );
                }

                if (isset($this->otherSettings['metric_is_active']) && filter_var($this->otherSettings['metric_is_active'], FILTER_VALIDATE_BOOLEAN)) {
                    if (empty($attribute['error']) && $isUsedAsAxis && isset($attribute['frontend_input']) && $attribute['frontend_input'] == "text") {
                        $deleteResponse = $this->deleteAttribute($data, $item['code']);
                        if (empty($deleteResponse['error'])) {
                            $this->addAttribute($data);
                        }
                    }
                }
                /* increment write count */
                if (!$errorMsg) {
                    $this->stepExecution->incrementSummaryInfo('write');
                }
            }
        }
    }

    protected function getAttributeByCode($code)
    {
        $method = 'GET';
        $url = $this->oauthClient->getApiUrlByEndpoint('updateAttributes', $this->storeViewCode);
        $url = str_replace('{attributeCode}', $code, $url);
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            return $results;
        } catch (\Exception $e) {
            $error = ['error' => json_decode($this->oauthClient->getLastResponse(), true) ];
            return $error;
        }
    }

    protected function addAttribute(array $resource, $code = null)
    {
        if (!empty($resource[self::AKENEO_ENTITY_NAME]['attribute_code'])) {
            $resource[self::AKENEO_ENTITY_NAME]['attribute_code'] = strtolower($resource[self::AKENEO_ENTITY_NAME]['attribute_code']);
        }
        if ($code) {
            $code = strtolower($code);
            $method = 'PUT';
            $url = $this->oauthClient->getApiUrlByEndpoint('updateAttributes', $this->storeViewCode);
            $url = str_replace('{attributeCode}', $code, $url);
        } else {
            $method = 'POST';
            $url = $this->oauthClient->getApiUrlByEndpoint('attributes', $this->storeViewCode);
        }

        try {
            $this->oauthClient->fetch($url, json_encode($resource), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            $this->removeEmptyOptions($code, $results['options']);
            
            return $results;
        } catch (\Exception $e) {
            $info = $this->oauthClient->getLastResponseInfo();
            $error = [
                    'error' => json_decode($this->oauthClient->getLastResponse(), true),
                    'code' => isset($info['http_code']) ? $info['http_code'] : 0
                ];
                
            return $error;
        }
    }

    public function deleteAttribute(array $resource, $code = null)
    {
        $code = strtolower($code);
        $method = 'DELETE';
        $url = $this->oauthClient->getApiUrlByEndpoint('deleteAttributes', $this->storeViewCode);
        $url = str_replace('{attributeCode}', $code, $url);

        try {
            $this->oauthClient->fetch($url, json_encode($resource), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            
            return $results;
        } catch (\Exception $e) {
            $info = $this->oauthClient->getLastResponseInfo();
            $error = [
                    'error' => json_decode($this->oauthClient->getLastResponse(), true),
                    'code' => isset($info['http_code']) ? $info['http_code'] : 0
                ];

            return $error;
        }
    }
    
    protected function removeEmptyOptions($code, $options)
    {
        if (!empty($options)) {
            foreach ($options as $option) {
                if (trim($option['value']) && !trim($option['label'])) {
                    $this->removeOption($code, $option['value']);
                }
            }
        }
    }

    protected function removeOption($attributeCode, $optionId)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('deleteAttributeOption');
        $url = str_replace('{attributeCode}', $attributeCode, $url);
        $url = str_replace('{option}', $optionId, $url);
        $method = 'DELETE';

        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = $this->oauthClient->getLastResponse();
            return $results;
        } catch (\Exception $e) {
            $error = ['error' => json_decode($this->oauthClient->getLastResponse(), true) ];
            return $error;
        }
    }

    /**
     * modify the images attribute scope
     */
    protected function modifyImagesAttributeScope()
    {
        if ($this->modifiedScope === false) {
            $images = ['image', 'thumbnail', 'small_image', 'swatch_image'];
            $data = [];
            
            foreach ($images as $imageCode) {
                $response = $this->getAttributeByCode($imageCode);
                if (!empty($response) && isset($response['scope']) && $response['scope'] !== 'global') {
                    $response['scope'] = 'global';
                    $data[self::AKENEO_ENTITY_NAME] = $response;
                    $response = $this->addAttribute($data, $imageCode);
                }
            }
            $this->modifiedScope = true;
        }
    }

    protected $matcher = [
        // akeneo_value              magento_value
        'wysiwyg_enabled'        => 'is_wysiwyg_enabled',
        // 'useable_as_grid_filter' => 'is_filterable',
        // 'useable_as_grid_filter2' => 'is_filterable_in_search',
        'unique'                 => 'is_unique',
        'code'                   => 'attribute_code',
        'type'                   => 'frontend_input',

        'name'                   => 'default_frontend_label',
        'type'                   => 'frontend_input',
        'frontend_labels'        => 'frontend_labels',
        // 'sort_order'             => 'sort_order' //label, value, sort_order
        // 'validation_rule'        => 'validation_rules'
    ];

    protected $fillers = [
        'entity_type_id'           => 0,
        // 'is_comparable'            => 0,
        // 'is_used_for_promo_rules'  => 0,
        // 'used_in_product_listing'  => 1,
        'is_searchable'            => 0,
        'is_visible'               => true,
        'is_visible_on_front'      => true,
    ];


    protected $attributeTypes = [
        // 'pim_catalog_identifier' => 'text',
        'pim_catalog_text'          => 'text',
        'pim_catalog_number'        => 'text',
        'pim_catalog_textarea'      => 'textarea',
        'pim_catalog_date'          => 'date',
        'pim_catalog_boolean'       => 'boolean',
        'pim_catalog_multiselect'   => 'multiselect',
        'pim_catalog_simpleselect'  => 'select',
        // 'pim_catalog_image'         => 'media_image',
        'pim_catalog_price_collection' => 'price',

        'pim_catalog_metric'        => 'text',
        // 'pim_catalog_file'          => 'media_image'
        // 'weee', //tax
        // 'swatch_visual', // visual swatch
        // 'swatch_text', // text swatch
    ];
}
