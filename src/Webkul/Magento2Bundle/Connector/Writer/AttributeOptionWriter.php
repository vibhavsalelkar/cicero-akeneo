<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$versionCompatibily = new AkeneoVersionsCompatibility();
$versionCompatibily->checkVersionAndCreateClassAliases();


/**
 * Add attribute options to magento2 Api
 * using single reqeust and adding labels in that, fetches values in case of exiting values
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class AttributeOptionWriter extends BaseWriter implements \ItemWriterInterface
{
    use DataMappingTrait;
    
    const AKENEO_ENTITY_NAME = 'option';
    const ERROR_ALREADY_EXIST = '%1 already exists.';
    const ERROR_DELETED = 'Cannot save attribute %1';

    protected $fetchedOptions = [];

    protected $channelRepository;

    public function __construct(\Doctrine\ORM\EntityManager $em, Magento2Connector $connectorService)
    {
        $this->em = $em;
        $this->connectorService = $connectorService;
    }

    /**
     * write attribute options to magento2 Api
     */
    public function write(array $items)
    {
        $parameters = $this->getParameters();
        $storeMapping = $this->getStoreMapping();
        $otherSettings = $this->connectorService->getSettings();
        $storeViews = $this->getCredentials()['storeViews'] ?? [];
        $storeMappingIds = [];
        foreach ($storeViews as $storeView) {
            $storeMappingIds[$storeView['code']] = $storeView['id'];
        }
        
        while (count($items)) {
            $item = array_shift($items);
            $item['attribute'] = strtolower($item['attribute']);
            $item['code'] = strtolower($item['code']);

            if ($imageURL = $this->connectorService->getAttrVisualSwatchImageURL($item['attribute'], $item['code'])) {
                $item['image_url'] = $imageURL;
            }
            $errorMsg = false;
            $updateTrack = null;
            $locale = in_array($this->defaultLocale, array_keys($item['labels'])) ? $this->defaultLocale : array_keys($item['labels'])[0];
            
            if (isset($otherSettings['attrOptionAdminCol']) && $otherSettings['attrOptionAdminCol'] == "1") {
                $name = $item['code'];
            } else {
                $name = $item['labels'][$locale] ?? $item['code'];
            }

            $this->storeViewCode = '';
            /* multi locale */
            $item['store_labels'] = [];
            
            foreach ($storeMappingIds as $storeViewCode => $storeGroupId) {
                $locale = !empty($storeMapping[$storeViewCode]['locale']) ? $storeMapping[$storeViewCode]['locale'] : $this->defaultLocale;
                /* get storeViewId by code and then use in data */
                if (!empty($item['labels'][$locale])) {
                    $item['store_labels'][] = [
                        'store_id' => $storeGroupId,
                        'label'    => $storeGroupId === 0 ? $name : $item['labels'][$locale],
                    ];
                }
            }
            
            $mapping = $this->getMappingByCode($item['code'] . '(' . $item['attribute'] . ')');
            $data = $this->createArrayFromDataAndMatcher($item, $this->matcher, SELF::AKENEO_ENTITY_NAME);
            if (!empty($item['image_url'])) {
                $data[SELF::AKENEO_ENTITY_NAME]['image_url'] = $item['image_url'];
            }
            
            if (!$mapping) {
                $existingId = $this->checkExistingId($item);
                if ($existingId !== null) {
                    $mapping = $this->addMappingByCode($item['code'] . '(' . $item['attribute'] . ')', $existingId);
                }
            }

            if ($mapping) {
                $updateTrack = $this->connectorService->getEntityTrackByEntityAndCode(self::AKENEO_ENTITY_NAME, $item['code']);
                if (!empty($parameters['addNewOnly']) && !$updateTrack) {
                    $this->stepExecution->incrementSummaryInfo('already_exported');
                    continue;
                }
                /* update resource */
                if ($mapping->getExternalId()) {
                    $existingOptions = $this->existingAttributeOptions($item['attribute']);
    
                    if (in_array($mapping->getExternalId(), $existingOptions)) {
                        $data[self::AKENEO_ENTITY_NAME]['value'] = $mapping->getExternalId();
                        $attribute = $this->addAttributeOption($data, $item['attribute']);
                        if (!empty($attribute['error']) && $attribute['code'] == Response::HTTP_BAD_REQUEST) {
                            $mapping = $this->deleteMapping($mapping);
                        }
                    } else {
                        $mapping = $this->deleteMapping($mapping);
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
                /* add resource */
                $option = $this->addAttributeOption($data, $item['attribute']);
                if (!isset($option['error'])) {
                    $getOptions = $this->getAttributeOptions($item['attribute'], true);
                    if (is_array($getOptions) && empty($getOptions['error'])) {
                        $labelId = $this->searchLabelAndRemove($name, $getOptions, $item['attribute']);
                        if ($labelId) {
                            $this->addMappingByCode($item['code'] . '(' . $item['attribute'] . ')', $labelId);
                        }
                    }
                } else {
                    $errorMsg = true;
                    $this->stepExecution->addWarning('Skipping', [], new \DataInvalidItem([
                        'Attribute Option'=> $data,
                        'Error' => $option['error']['message'],
                    ]));
                }
                /* increment write count */
                if (!$errorMsg) {
                    $this->stepExecution->incrementSummaryInfo('write');
                }
            }
        }
    }

    protected function checkExistingId($item)
    {
        $attributeMappings = $this->connectorService->getAttributeMappings();
        $attributeMappings = array_flip($attributeMappings);
        $externalAttrCode = !empty($attributeMappings[$item['attribute']]) ? $attributeMappings[$item['attribute']] : $item['attribute'];
        $externalAttrCode = strtolower($externalAttrCode);
                
        $getOptions = $this->getAttributeOptions($externalAttrCode);

        $existingId = null;
        if ($getOptions && empty($getOptions['error'])) {
            $lowercaseLabels = array_map('strtolower', $item['labels']);
            $lowercaseLabels[] = $item['code'];

            foreach ($getOptions as $getOption) {
                if (!empty(trim($getOption['label'])) && (
                    strtolower($getOption['label']) === $item['code'] ||
                        in_array(
                            strtolower($getOption['label']),
                            $lowercaseLabels
                        )
                )) {
                    $existingId = $getOption['value'];
                    break;
                }
            }
        }

        return $existingId;
    }

    protected function searchLabelAndRemove($label, $allOptions, $attributeCode)
    {
        $id = null;
        foreach ($allOptions as $option) {
            if (!empty(trim($option['label'])) && $option['label'] == $label) {
                if (!$id) {
                    $id = $option['value'];
                } elseif (empty(trim($option['label']))) {
                    $this->removeOption($attributeCode, $option['value']);
                }
            }
        }
        return $id;
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

    protected function getAttributeOptions($attributeCode, $noCache = false)
    {
        if (!$noCache && !empty($this->fetchedOptions[$attributeCode])) {
            return $this->fetchedOptions[$attributeCode];
        }
        
        $url = $this->oauthClient->getApiUrlByEndpoint('attributeOption');
        $url = str_replace('{attributeCode}', $attributeCode, $url);
        $method = 'GET';

        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            $this->fetchedOptions[$attributeCode] = $results;

            return $results;
        } catch (\Exception $e) {
            $error = ['error' => json_decode($this->oauthClient->getLastResponse(), true) ];
            
            return $error;
        }
    }

    protected function addAttributeOption(array $resource, $attributeCode)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('attributeOption');
        $url = str_replace('{attributeCode}', $attributeCode, $url);

        $method = array_key_exists("value", $resource[self::AKENEO_ENTITY_NAME]) ? 'PUT' : 'POST';

        try {
            $this->oauthClient->fetch($url, json_encode($resource), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            
            return str_replace("id_", "", $results);
        } catch (\Exception $e) {
            $info = $this->oauthClient->getLastResponseInfo();
            $error = [
                'error' => json_decode($this->oauthClient->getLastResponse(), true),
                'code'  => isset($info['http_code']) ? $info['http_code'] : 0
                ];

            return $error;
        }
    }
    public function existingAttributeOptions($attributeCode)
    {
        $attributeOptions = $this->getAttributeOptions($attributeCode);
        $existingOptions = [];
        if (!empty($attributeOptions)) {
            $existingOptions = array_column($attributeOptions, 'value');
        }

        return $existingOptions;
    }
    protected $matcher = [
        // akeneo_key     =>           external_key
        'sort_order'             => 'sort_order',
        'code'                   => 'label',
        'store_labels'           => 'store_labels',
    ];
}
