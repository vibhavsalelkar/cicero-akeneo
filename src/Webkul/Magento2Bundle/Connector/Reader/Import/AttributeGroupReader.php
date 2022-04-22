<?php

namespace Webkul\Magento2Bundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Connector\Reader\Import\BaseReader;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * import attribute groups from Magento 2
 *
 * @author    webkul <support@webkul.com>
 * @copyright 2010-18 Webkul (http://store.webkul.com/license.html)
 */
class AttributeGroupReader extends BaseReader implements \ItemReaderInterface, \StepExecutionAwareInterface, \InitializableInterface
{
    use DataMappingTrait;

    protected $oauthClient;

    protected $jsonHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

    protected $itemIterator;

    protected $mappedFields;

    protected $storeMapping;

    protected $attributeSetIds;

    protected $currentPage;

    protected $attributesCode;

    protected $items;

    protected $firstRead;

    protected $totalCount;

    const AKENEO_ENTITY_NAME = 'group';

    const PAGE_SIZE = 1;

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->attributesCode = []; // for unique attributeCode
        $credentials = $this->connectorService->getCredentials();

        if (!$this->oauthClient) {
            $this->oauthClient = new OAuthClient($credentials['authToken'], $credentials['hostName']);
        }

        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $channelCode = !empty($filters['structure']['scope']) ? $filters['structure']['scope'] : '';
        $channel = $channelCode ? $this->connectorService->findChannelByIdentifier($channelCode) : null;
        $this->currentPage = 1;
        $this->items = $this->getAttributeGroupsByPageSize($this->currentPage);
        $this->firstRead = false;
    }

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

        if ($item !== null) {
            $this->stepExecution->incrementSummaryInfo('read');
            $this->itemIterator->next();
        } else {
            $items = [];

            while (empty($items) && $this->totalCount && $this->currentPage * self::PAGE_SIZE <= $this->totalCount) {
                $items = $this->getAttributeGroupsByPageSize(++$this->currentPage);
            }

            $this->itemIterator = new \ArrayIterator($items);
            $item = $this->itemIterator->current();
            
            if ($item !== null) {
                $this->stepExecution->incrementSummaryInfo('read');
                $this->itemIterator->next();
            }
        }
        
        return $item;
    }

    // format data according to akeneo processor
    protected function formateData($attributeGroups)
    {
        $items = [];
        foreach ($attributeGroups as $attributeGroupId => $attributeGroup) {
            $attributeGroupName = $attributeGroup['attribute_group_name'] ? $attributeGroup['attribute_group_name'] : '';
            $code = $this->connectorService->convertToCode($attributeGroupName);
            if (in_array($code, $this->attributesCode)) {
                continue;
            } else {
                $this->attributesCode[] = $code;
            }

            $attributeGroupEntity = $this->connectorService->getAttributeGroupByCode($code);
            
            if ($attributeGroupEntity) {
                $this->stepExecution->incrementSummaryInfo('read');
                $this->stepExecution->incrementSummaryInfo('already_exist');
                continue;
            }

            $formatted = [
                "code" => $code,
                "attributes" => [],
                "sort_order" => "0",
            ];

            $storeMapping = $this->connectorService->getStoreMapping();
            foreach ($storeMapping as $storeCode => $storeData) {
                if (!empty($storeData['locale'])) {
                    $formatted['labels'][$storeData['locale']] = !empty($attributeGroup['attribute_group_name']) ? $attributeGroup['attribute_group_name'] : '' ;
                }
            }
            
            // Add to Mapping in Database
            $externalId = !empty($attributeGroup['attribute_group_id']) ? $attributeGroup['attribute_group_id'] : null;
            $relatedId = !empty($attributeGroup['attribute_set_id']) ? $attributeGroup['attribute_set_id'] : null;
            if ($code && $externalId && $relatedId) {
                $mapping = $this->addMappingByCode($code, $externalId, $relatedId, $this::AKENEO_ENTITY_NAME);
            }

            $items[] = $formatted;
        }
        
        return $items;
    }

    protected function getAttributeGroups($attributeSetId)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getAttributeGroup');
        $url = str_replace('?searchCriteria=', '?searchCriteria[filterGroups][0][filters][0][field]=attribute_set_id&searchCriteria[filterGroups][0][filters][0][value]='.$attributeSetId, $url);
        $method = 'GET';
        
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            
            return $results;
        } catch (\Exception $e) {
            $lastResponse = json_decode($this->oauthClient->getLastResponse(), true);
            $this->stepExecution->addWarning("Error! can't get attribute group", [], new \DataInvalidItem([
                "Request URL" => $url,
                "Request Method" => $method,
                "Response" => !empty($lastResponse['message']) ? $lastResponse['message'] : '',
                "debug_Line" => __LINE__,
            ]));
        }

        return [];
    }

    protected function normalizeAttributeGroupsByName($attributeGroupSet)
    {
        foreach ($attributeGroupSet["items"] as $attributeGroup) {
            $attributeGroupName[] = $attributeGroup;
        }

        return $attributeGroupName;
    }

    protected function getAttributeSetIds($currentPage)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getAttributeSets');
        $url = strstr($url, "[pageSize]", true) . '[pageSize]='.self::PAGE_SIZE.'&searchCriteria[currentPage]='.$currentPage;
        $method = 'GET';
        $attributeSetIds = [];

        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            
            if (isset($results['total_count'])) {
                $this->totalCount = $results['total_count'];
            } else {
                $results = $this->getAttributeSetIds($currentPage++);
            }

            if ($currentPage * self::PAGE_SIZE <= $this->totalCount) {
                if (!empty($results['items'])) {
                    foreach ($results['items'] as $attributeSet) {
                        $attributeSetIds[] = $attributeSet['attribute_set_id'] ? $attributeSet['attribute_set_id'] : '';
                    }
                }
            } else {
                $restPage = (($currentPage * self::PAGE_SIZE) - $this->totalCount);
                if ($restPage <  self::PAGE_SIZE) {
                    if (!empty($results['items'])) {
                        foreach ($results['items'] as $attributeSet) {
                            $attributeSetIds[] = $attributeSet['attribute_set_id'] ? $attributeSet['attribute_set_id'] : '';
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return $attributeSetIds;
    }

    private function getAttributeGroupsByPageSize($currentPage)
    {
        $items = [];
        $attributeSetIds =  $this->getAttributeSetIds($currentPage);
        foreach ($attributeSetIds as $attributeSetId) {
            if (!empty($attributeSetId)) {
                $attributeGroups = $this->normalizeAttributeGroupsByName($this->getAttributeGroups($attributeSetId));
                $attributeGroups = $this->formateData($attributeGroups);
                
                if (!empty($attributeGroups) && is_array($attributeGroups)) {
                    $items = array_merge($attributeGroups, $items);
                }
            }
        }
        
        return $items;
    }
}
