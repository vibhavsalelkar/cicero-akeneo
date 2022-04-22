<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();
/**
 * Add families to magento 2 as attribute sets by magento 2 Api
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class AttributeSetWriter extends BaseWriter implements \ItemWriterInterface
{
    use DataMappingTrait;
    
    const AKENEO_ENTITY_NAME = 'family';
    const ERROR_EXISTS = 'An attribute set named';
    const ATTRIBUTE_SET_ADD_ERROR = 'Cannot save attributeGroup';
    const GROUP_CODE = 'attributes_akeneo';
    const GROUP_NAME = 'Attributes';

    protected $defaultAttributeSetId;
    protected $reservedKeys = null;
    protected $fallbackAttributesetValue = 4;
    protected $exportAllGroups;

    public function __construct(\Doctrine\ORM\EntityManager $em, Magento2Connector $connectorService)
    {
        $this->em = $em;
        $this->connectorService = $connectorService;
    }

    /**
     * write attributeSets to magento2 Api
     */
    public function write(array $items)
    {
        $parameters = $this->getParameters();
        $otherSettings = $this->connectorService->getSettings();
        $this->exportAllGroups = !empty($otherSettings['exportAttributeGroup']);
        if (!in_array($this->defaultLocale, $parameters['filters']['structure']['locales'])) {
            $this->stepExecution->addWarning('Invalid Job', [], new \DataInvalidItem([$this->defaultLocale. ' default store view locale is not added in job']));
            $this->stepExecution->setTerminateOnly();
            $items = [];
        }
        while (count($items)) {
            $item = array_shift($items);
            $item['code'] = strtolower($item['code']);

            $errorMsg = false;
            $updateTrack = null;
            $itemForMagento = [
                'attributeSet' => [
                    "attribute_set_name" => $item['code'],
                    "sort_order"         => 0,
                ]
            ];
            $mapping = $this->getMappingByCode($item['code']);
            
            if ($mapping) {
                if (!$this->checkAttributeSetFromApi($mapping->getExternalId())) {
                    $mapping = $this->deleteMapping($mapping);
                    $this->connectorService->deleteFamilyRelatedMappingsByCodeEntityAndUrl($item['code'], 'attributeLink', $this->credentials['hostName']);
                }
            }
            
            if (!$mapping) {
                /* add attributeSet */
                $itemForMagento['skeletonId'] = ($this->getDefaultAttributeSetId());
                $resource = $this->postAttributeSetToApi($itemForMagento);
                
                if (!empty($resource['attribute_set_id'])) {
                    $id = $resource['attribute_set_id'];
                    $this->addMappingByCode($item['code'], $id, $resource['entity_type_id']);
                    $this->linkAttributesToAttributeSet($item, $id);
                } elseif (isset($resource['error']['http_code']) && Response::HTTP_BAD_REQUEST == $resource['error']['http_code']) {
                    /* existing family */
                    $attrSets = $this->getAndAddAttributeSets();
                    $mapping = $this->getMappingByCode($item['code']);
                } else {
                    $this->stepExecution->addWarning('Error! while exporting family', [], new \DataInvalidItem([
                         'http_code' => $resource['error']['http_code'] ?? '',
                        'apiResponse' => json_encode($resource)
                    ]));
                }
            }

            if ($mapping) {
                $id = $mapping->getExternalId();
                $skeletonId = $mapping->getRelatedId();
                $itemForMagento['attributeSet']['entity_type_id'] = $skeletonId;
                $itemForMagento['attributeSet']['attribute_set_id'] = $id;
                
                $updateTrack = $this->connectorService->getEntityTrackByEntityAndCode(self::AKENEO_ENTITY_NAME, $item['code']);
                if (!empty($parameters['addNewOnly']) && !$updateTrack) {
                    // $this->linkAttributesToAttributeSet($item, $id );
                    $this->stepExecution->incrementSummaryInfo('already_exported');
                    continue;
                }

                $attributeSet = $this->postAttributeSetToApi($itemForMagento, 'update', $id);

                if ((!empty($attributeSet['error']['http_code'])) && $attributeSet['error']['http_code'] == Response::HTTP_NOT_FOUND) {
                    // $this->handleDeletedEntity($item, $itemForMagento, $mapping);
                } else {
                    $this->linkAttributesToAttributeSet($item, $id);
                }
            }
            if (!empty($updateTrack)) {
                $this->connectorService->removeTrack($updateTrack);
            }

            /* increment write count */
            if (!$errorMsg) {
                $this->stepExecution->incrementSummaryInfo('update');
            }
        }
    }

    protected function linkAttributesToAttributeSet($item, $attributeSetId)
    {
        if (!$this->reservedKeys) {
            $attributeMappings = $this->connectorService->getAttributeMappings();
            $this->reservedKeys = array_merge(
                array_unique(array_filter(array_values($attributeMappings))),
                array_keys($attributeMappings),
                $this->reservedAttributes
            );
        }

        // $alreadyLinkedAttributes = $this->getAttributesInAttributeSet($attributeSetId);
        $alreadyLinkedAttributes = [];

        if (!empty($item['attributes'])) {
            $otherMappings = $this->connectorService->getOtherMappings();
            $mappedAttributes = !empty($otherMappings['custom_fields']) ? $otherMappings['custom_fields'] : [];
            $familyAttributes = $this->connectorService->getFamilyAttributesByCode($item['code']);
            $attributes = array_intersect($familyAttributes, $mappedAttributes);
            
            foreach ($attributes as $key => $attributeCode) {
                if (in_array($attributeCode, $alreadyLinkedAttributes) || in_array($attributeCode, $this->reservedKeys)) {
                    continue;
                }
                $attrMapping = $this->getMappingByCode($attributeCode, 'attribute');
                
                if (!$attrMapping) {
                    continue;
                }

                if ($this->exportAllGroups) {
                    $groupData = $this->connectorService->getGroupByAttributeCode($attributeCode);
                }
                if (empty($groupData)) {
                    $groupCode = self::GROUP_CODE;
                    $groupName = self::GROUP_NAME;
                } else {
                    $groupCode = $groupData->getCode();
                    $groupData->setLocale($this->defaultLocale);
                    $groupName = $groupData->getLabel();
                    $groupName = trim($groupName, '[]');
                }

                $groupId = null;
                $groupMapping = $this->getGroupMappingByCode($groupCode, $attributeSetId);

                if (!$groupMapping) {
                    $gData = [
                        'attribute_set_id'       => $attributeSetId,
                        'attribute_group_name'  => $groupName,
                        'extension_attributes' => [
                            'attribute_group_code' => $groupCode
                        ],
                    ];
                    $group = $this->addGroup(['group' => $gData]);
                    
                    if (!empty($group['error']['message'])) {
                        $group = $this->getAttributeGroupByName($groupName, $attributeSetId);
                    }
                    if (!empty($group['attribute_group_id'])) {
                        $this->addMappingByCode($groupCode, $group['attribute_group_id'], $group['attribute_set_id'], 'group');
                        $groupId = $group['attribute_group_id'];
                    } else {
                        $this->stepExecution->addWarning('Error! while adding attribute group '. $groupName, [], new \DataInvalidItem([
                            'code' => $item['code'],
                            'apiResponse' => json_encode($group)
                        ]));
                    }
                } else {
                    $groupId = $groupMapping->getExternalId();
                }

                if (!empty($groupId)) {
                    $linkMapping = $this->getMappingByCode(strtolower($attributeCode) . '-' . $groupId . '-' . $attributeSetId, 'attributeLink', false, $this->stepExecution->getJobExecution()->getId());
                    if (!$linkMapping) {
                        $data = [
                            "attributeSetId"   => $attributeSetId,
                            "attributeGroupId" => $groupId,
                            "attributeCode"    => strtolower($attributeCode),
                            "sortOrder"        => $key
                        ];
                        $result = $this->addAttributeToAttributeSet($data);

                        if (intval($result) !== 0) {
                            $this->stepExecution->addSummaryInfo("linked attribute", "$attributeCode to attributeSetId: $attributeSetId");
                            $this->addMappingByCode(strtolower($attributeCode) . '-' . $groupId . '-' . $attributeSetId, intval($result), null, 'attributeLink');
                        }
                    }
                }
            }
        }
    }

    protected function getAttributesInAttributeSet($attributeSetId)
    {
        $method = 'GET';
        $url = $this->oauthClient->getApiUrlByEndpoint('getAttributeSet');
        $url = str_replace('{attributeSetId}', $attributeSetId, $url);

        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $results = [];
        }

        $codes = [];
        foreach ($results as $result) {
            $codes[] = $result['attribute_code'];
        }

        return $codes;
    }

    protected function addGroup(array $resource)
    {
        $method = 'POST';
        $url = $this->oauthClient->getApiUrlByEndpoint('addAttributeGroup');

        try {
            $this->oauthClient->fetch($url, json_encode($resource), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);

            return $results;
        } catch (\Exception $e) {
            $error = ['error' => json_decode($this->oauthClient->getLastResponse(), true) ];
            return $error;
        }
    }

    protected function addAttributeToAttributeSet($data)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('addToAttributeSet');
        $method = 'POST';
        try {
            $this->oauthClient->fetch($url, json_encode($data), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            return $results;
        } catch (\Exception $e) {
        }
    }

    protected function checkAttributeSetFromApi($attributeSetId)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('updateAttributeSet');
        $url = str_replace('{attributeSetId}', $attributeSetId, $url);
        
        $method = 'GET';
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $results = null;
        }

        return !empty($results);
    }

    /* post attributes to api */
    protected function postAttributeSetToApi(array $attributeSet, $action = 'add', $attributeId = null)
    {
        if ('update' == $action) {
            $url = $this->oauthClient->getApiUrlByEndpoint('updateAttributeSet');
            $url = str_replace('{attributeSetId}', $attributeId, $url);
            $method = 'PUT';
        } else {
            $url = $this->oauthClient->getApiUrlByEndpoint('addAttributeSet');
            $method = 'POST';
        }

        try {
            $this->oauthClient->fetch($url, json_encode($attributeSet), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            return $results;
        } catch (\Exception $e) {
            $lastResponse = json_decode($this->oauthClient->getLastResponse(), true);
            $responseInfo = $this->oauthClient->getLastResponseInfo();
            foreach (array_keys($responseInfo) as $key) {
                if (trim($key) == 'http_code') {
                    $lastResponse['http_code'] = $responseInfo[$key];
                    break;
                }
            }

            $error = ['error' => $lastResponse ];
            return $error;
        }

        return null;
    }

    private function getAndAddAttributeSets()
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getAttributeSets');
        $url = str_replace('[pageSize]=50', '[pageSize]=10000', $url);
        $method = 'GET';

        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $results = [];
        }

        if (!empty($results['items'])) {
            foreach ($results['items'] as $result) {
                $this->updateMappingByCode($result['attribute_set_name'], $result['attribute_set_id'], $result['entity_type_id']);
            }
        }
    }

    protected function getAttributeGroupByName($name, $attributeSetId)
    {
        $url = strstr($this->oauthClient->getApiUrlByEndpoint('getAttributeGroup'), '?', true);
        $method = 'GET';

        $url .= '?' . 'searchCriteria[filter_groups][0][filters][0][field]=attribute_set_id&searchCriteria[filter_groups][0][filters][0][value]='
                . $attributeSetId .  '&searchCriteria[filter_groups][0][filters][0][condition_type]=eq'
                . '&searchCriteria[filter_groups][1][filters][1][field]=attribute_group_name&searchCriteria[filter_groups][1][filters][1][value]='
                . urlencode($name) . '&searchCriteria[filter_groups][1][filters][1][condition_type]=eq';
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $lastResponse = json_decode($this->oauthClient->getLastResponse(), true);
            $results = ['error' => $lastResponse ];
        }

        foreach ($results['items'] as $result) {
            if (!empty($result['attribute_group_name']) && strtolower($result['attribute_group_name']) === strtolower($name)) {
                return $result;
            }
        }

        return !empty($results['items'][0]) ? $results['items'][0] : $results;
    }

    protected function getDefaultAttributeSetId()
    {
        if (empty($this->defaultAttributeSetId)) {
            $url = $this->oauthClient->getApiUrlByEndpoint('getAttributeSets');
            $url = strstr($url, '?', true) . '?searchCriteria[pageSize]=3';
            $method = 'GET';

            try {
                $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
                $results = json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
            }
            $defaultValue = null;
            if (isset($results)) {
                foreach ($results['items'] as $resultItem) {
                    if ($defaultValue) {
                        if ($defaultValue > $resultItem['entity_type_id']) {
                            $defaultValue = $resultItem['entity_type_id'];
                        }
                    } else {
                        $defaultValue = $resultItem['entity_type_id'];
                    }
                }
            }
            $this->defaultAttributeSetId = $defaultValue ? : $this->fallbackAttributesetValue;
        }

        return $this->defaultAttributeSetId;
    }

    protected function getGroupMappingByCode($code, $attributeSetId)
    {
        $mapping = $this->mappingRepository->findOneBy([
            'code' => $code,
            'entityType' => 'group',
            'relatedId' => $attributeSetId,
            'apiUrl' => $this->getApiUrl(),
        ]);

        return $mapping;
    }
}
