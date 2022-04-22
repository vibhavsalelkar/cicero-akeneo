<?php

namespace Webkul\Magento2Bundle\Step;

use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * magento2 step implementation that read items, process them and write them using api, code in respective files
 *
 */
class MetricTypeAttributeOption extends \AbstractStep
{
    use DataMappingTrait;

    const AKENEO_ENTITY_NAME = 'option';

    protected $configurationService;

    protected $em;

    protected $jsonHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

    protected $oauthClient;

    protected $credentials;

    protected $stepExecution;

    /**
     * @param string                   $name
     * @param EventDispatcherInterface $eventDispatcher
     * @param \JobRepositoryInterface    $jobRepository
     */
    public function __construct(
        $name,
        EventDispatcherInterface $eventDispatcher,
        \JobRepositoryInterface $jobRepository,
        Magento2Connector $configurationService,
        \Doctrine\ORM\EntityManager $em
    ) {
        parent::__construct($name, $eventDispatcher, $jobRepository);
        $this->configurationService = $configurationService;
        $this->em = $em;
    }
    /**
     * {@inheritdoc}
     */
    public function doExecute(\StepExecution $stepExecution)
    {
        $this->mappingRepository = $this->em->getRepository('Magento2Bundle:DataMapping');

        try {
            $this->configurationService->setStepExecution($stepExecution);
            if (null == $this->credentials) {
                $credentials = $this->configurationService->getCredentials();
                $this->credentials = $credentials;
            }
            
            $host = !empty($this->credentials['hostName']) ? $this->credentials['hostName'] : 'Not Found';
            $stepExecution->addSummaryInfo('host', $host);
            $this->stepExecution = $stepExecution;
            $storeViews = json_decode($this->configurationService->validateCredentials(), true);
            $this->oauthClient = new OAuthClient($credentials['authToken'], $credentials['hostName']);

            $mappings = $this->configurationService->getOtherMappings();
            $attrMappings = $this->configurationService->getAttributeMappings();
            if (isset($attrMappings['website_ids'])) {
                unset($attrMappings['website_ids']);
            }

            $mappedStandard = array_values($attrMappings);
            $mappedCustom = !empty($mappings['custom_fields']) ? $mappings['custom_fields'] : [];
            $mappedAll = array_merge($mappedCustom, $mappedStandard);

            $attributesAxesOptions = $this->configurationService->attributesAxesOptions();
            
            $selectedAttrs = array_intersect_key($attributesAxesOptions, array_flip($mappedAll));
            $data = [];
            foreach ($selectedAttrs as $attribute => $selectAttr) {
                if (is_array($selectAttr)) {
                    foreach ($selectAttr as $index => $value) {
                        $data['option'] = [
                            "sort_order" => $index,
                            "label" => $value,
                            "store_labels" => [
                                "value" => $value
                            ],
                        ];
                        $resultResponse = $this->mappingForMetricAttribute($data, $attribute);
                        if (isset($resultResponse['error'])) {
                            $stepExecution->addWarning($resultResponse['message'], [], new \DataInvalidItem([]));
                            $stepExecution->setTerminateOnly();
                        };
                    }
                }
            }
        } catch (Exception $e) {
            $stepExecution->addWarning('Invalid Credential or Expired', [], new \DataInvalidItem([]));
            $stepExecution->setTerminateOnly();
        }
    }
 
    protected function mappingForMetricAttribute($option, $attributeCode)
    {
        $mappingCode = $option['option']['label'] . '(' . $attributeCode . ')';
        $mapping = $this->getMappingByCode($mappingCode);
        $data = $this->createArrayFromDataAndMatcher($option, $this->matcher, SELF::AKENEO_ENTITY_NAME);
            
        if (!$mapping) {
            $existingId = $this->checkExistingId($option, $attributeCode);
              
            if ($existingId !== null) {
                $mapping = $this->addMappingByCode($option['option']['label'] . '(' . $attributeCode. ')', $existingId);
            }
        }
        if ($mapping) {
            $updateTrack = $this->configurationService->getEntityTrackByEntityAndCode(self::AKENEO_ENTITY_NAME, $option['option']['label']);
            if (!$updateTrack) {
                $this->stepExecution->incrementSummaryInfo('already_exported');
            }
            /* update resource */
            if ($mapping->getExternalId()) {
                $data[self::AKENEO_ENTITY_NAME]['value'] = $mapping->getExternalId();

                $attribute = $this->addAttributeOption($data, $attributeCode);
                if (!empty($attribute['error']) && isset($attribute['code']) ? $attribute['code'] : "" == Response::HTTP_BAD_REQUEST) {
                    $mapping = $this->deleteMapping($mapping);
                }
            }
        }
        if (!empty($updateTrack)) {
            $this->configurationService->removeTrack($updateTrack);
        }

        if (!$mapping) {
            /* add resource */
            $addOptionResult = $this->addAttributeOption($option, $attributeCode);

            if (!isset($addOptionResult['error'])) {
                $getOptions = $this->getAttributeOptions($attributeCode, true);
                if (is_array($getOptions) && empty($getOptions['error'])) {
                    $labelId = $this->searchLabelAndRemove($option['option']['label'], $getOptions, $attributeCode);
                       
                    if ($labelId) {
                        $this->addMappingByCode($option['option']['label'] . '(' . $attributeCode . ')', $labelId);
                    }
                }
            }
        }
    }
    protected function checkExistingId($optionData, $attributeCode)
    {
        $attributeMappings = $this->configurationService->getAttributeMappings();
        $attributeMappings = array_flip($attributeMappings);
        $externalAttrCode = !empty($attributeMappings[$attributeCode]) ? $attributeMappings[$attributeCode] : $attributeCode;
        $externalAttrCode = strtolower($externalAttrCode);
        
        $getOptions = $this->getAttributeOptions($externalAttrCode);
       
        $existingId = null;
        if ($getOptions && empty($getOptions['error'])) {
            foreach ($getOptions as $getOption) {
                if (!empty(trim($getOption['label'])) && (strtolower($getOption['label']) === $optionData['option']['label'])) {
                    $existingId = $getOption['value'];
                    break;
                }
            }
        }

        return $existingId;
    }

    protected function getAttributeOptions($attributeCode)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('attributeOption');
        $url = str_replace('{attributeCode}', $attributeCode, $url);
        $method = 'GET';

        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
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
        $method = 'POST';
        try {
            $this->oauthClient->fetch($url, json_encode($resource), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
           
            return $results;
        } catch (\Exception $e) {
            $info = $this->oauthClient->getLastResponseInfo();
            $error = [
                'error' => json_decode($this->oauthClient->getLastResponse(), true),
                'message' => $e->getMessage(),
                'code'  => isset($info['http_code']) ? $info['http_code'] : 0
                ];
            return $error;
        }
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
    protected $matcher = [
        // akeneo_key     =>           external_key
        'sort_order'             => 'sort_order',
        'code'                   => 'label',
        'store_labels'           => 'store_labels',
    ];

    protected function getHostName()
    {
        try {
            $result = rtrim($this->credentials['hostName'], '/');
        } catch (\Exception $e) {
            $result = null;
        }
        
        return $result;
    }
    protected function createArrayFromDataAndMatcher($item, $matcher, $wrapper = null)
    {
        $data = [];
        foreach ($matcher as $akeneoKey => $externalKey) {
            if (array_key_exists($akeneoKey, $item)) {
                $data[$externalKey] = $item[$akeneoKey];
            }
        }

        return $wrapper ? [$wrapper => $data] : $data;
    }
}
