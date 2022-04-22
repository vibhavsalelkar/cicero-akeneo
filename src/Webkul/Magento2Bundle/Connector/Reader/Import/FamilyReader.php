<?php

namespace Webkul\Magento2Bundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\Connector\Reader\Import\BaseReader;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * import attribute-sets from from Magento 2
 *
 * @author    webkul <support@webkul.com>
 * @copyright 2010-18 Webkul (http://store.webkul.com/license.html)
 */
class FamilyReader extends BaseReader implements \ItemReaderInterface, \StepExecutionAwareInterface, \InitializableInterface
{
    use DataMappingTrait;

    private $familyObject;

    protected $locale;

    protected $jsonHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

    protected $itemIterator;

    protected $storeMapping;

    protected $identifierAttributeCode;

    protected $items;

    protected $firstRead;

    protected $channel;

    protected $attributeRequired;

    protected $familyIterator;

    protected $activeLocales;

    protected $familyRepo;

    protected $otherMapping;

    const AKENEO_ENTITY_NAME = 'family';

    /**
     * @param Magento2Connector            $connectorService
     * @param \Doctrine\ORM\EntityManager  $em
     * @param \FamilyController             $familyObject
     */
    public function __construct(
        Magento2Connector $connectorService,
        \Doctrine\ORM\EntityManager $em,
        \FamilyController $familyObject
    ) {
        parent::__construct($connectorService, $em);
        $this->familyObject = $familyObject;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        if (!$this->oauthClient) {
            $credentials = $this->connectorService->getCredentials();
            $this->oauthClient = new OAuthClient($credentials['authToken'], $credentials['hostName']);
        }

        $this->identifierAttributeCode = $this->connectorService->getIdentifierAttributeCode();
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $this->channel = !empty($filters['structure']['scope']) ? $filters['structure']['scope'] : '';
        $this->storeMapping = $this->connectorService->getStoreMapping();
        $this->activeLocales = $this->connectorService->getActiveLocales();
        $this->otherMapping = $this->connectorService->getOtherMappings();
        $attributeSets = $this->getAttributeSets();
        $items = [];

        if (!empty($attributeSets['items'])) {
            $this->familyIterator = new \ArrayIterator($attributeSets['items']);
            $families = [];

            for ($i = 0; $i<10 ; $i++) {
                $families[] = $this->familyIterator->current();
                $this->familyIterator->next();
            }

            if ($families) {
                $items = $this->formatData($families);
            }
        } elseif (!empty($attributeSets['error'])) {
            $this->stepExecution->addWarning('API Error: Unable to Fetch the Attribute sets', [], new \DataInvalidItem([
                'request URL' => $attributeSets['requestURL'],
                'request Method'    => $attributeSets['method'],
                'response' => $attributeSets['error']
            ]));
        }

        $this->items = $items;
        $this->firstRead = false;
    }


    public function read()
    {
        if ($this->itemIterator === null && $this->firstRead === false) {
            $this->itemIterator = new \ArrayIterator($this->items);
            $this->firstRead = true;
        }

        $item = $this->itemIterator->current();
        
        if ($item === null) {
            $families = [];
            for ($i= 0; $i<10 && null != $this->familyIterator ; $i++) {
                $families[] = $this->familyIterator->current();
                $this->familyIterator->next();
            }
            if ($families) {
                $this->items = $this->formatData($families);
                $this->itemIterator = new \ArrayIterator($this->items);
                $item = $this->itemIterator->current();
            }
        }

        if ($item !== null) {
            $this->stepExecution->incrementSummaryInfo('read');
            $this->itemIterator->next();
        }

        return  $item;
    }

    /**
     * Formate Attribute sets as Akeneo Families
     * @var array $attributeSets
     * @return array $results
    */
    protected function formatData($attributeSets)
    {
        $results = [];
        foreach ($attributeSets as $attributeSet) {
            if (!isset($attributeSet['attribute_set_name'])) {
                continue;
            }
            $familyCode = $this->connectorService->convertToCode($attributeSet['attribute_set_name'], false);
            if (empty($familyCode)) {
                continue;
            }

            $attribute_requirements = [];
            $attributesByFamily = [];
            $family = $this->connectorService->getFamilyByCode($familyCode);

            if ($family) {
                foreach ($this->activeLocales as $activeLocale) {
                    $locales[$activeLocale] = $activeLocale ? $family->setLocale($activeLocale)->getLabel(): $family->setLocale('en_US')->getLabel();
                }

                $attributeRequirements = $family->getAttributeRequirements();
                foreach ($attributeRequirements as $attributeRequirement) {
                    $channel = $attributeRequirement->getChannel()->getCode();
                    if (!empty($channel)) {
                        $attribute_requirements[$channel] = [$attributeRequirement->getAttribute()->getCode()];
                    }
                }
                
                $attributesByFamily = $family->getAttributeCodes();
            }
            
            $addAttributesinFamily = [];
            $optionName= [];
            //add image field
            $finalOtherMapping = [];

            if (!empty($this->otherMapping['images'])) {
                foreach ($this->otherMapping['images'] as $key => $value) {
                    $finalOtherMapping[$value] = $value;
                }
            }

            $imageAttributesCodes = [];

            foreach ($finalOtherMapping as $key => $attributes) {
                $imageAttributesCodes[] = $attributes;
            }

            $this->attributeRequired = [];
            $attributesCodes = $this->getAttributesByAttributeSetId($attributeSet['attribute_set_id']);

            if ($this->identifierAttributeCode) {
                $attributesCodes[] = $this->identifierAttributeCode;
            }

            if (!empty($this->otherMapping['images'][0])) {
                $attributesCodes[] =  $this->otherMapping['images'][0];
            }

            if ($attributesByFamily) {
                $attributesCodes = array_unique(array_merge($attributesCodes, $imageAttributesCodes, $attributesByFamily));
            }

            $channel = !empty($channel) ? $channel : $this->channel;
            
            if (empty($attribute_requirements[$this->channel])) {
                $attribute_requirements = [$this->channel=> []];
            }

            $attribute_requirements[$this->channel] = array_merge($attribute_requirements[$this->channel], $this->attributeRequired);
            
            $result = [
                'code' => !empty($family) ? $family->getCode() : $familyCode,
                'attribute_as_image' => !empty($this->otherMapping['images'][0]) ? $this->otherMapping['images'][0] :'',
                'attribute_as_label' => $this->identifierAttributeCode,
                'attributes' => $attributesCodes,
                'attribute_requirements' => $attribute_requirements
            ];
            
            //set locale
            $locale = '';
            if ($this->defaultLocale) {
                $locale = $this->defaultLocale;
            } else {
                foreach ($this->storeMapping as $storeCode => $value) {
                    if ($value['locale']) {
                        $locale = $value['locale'];
    
                        break;
                    } else {
                        continue;
                    }
                }
            }
            
            $locale = $locale ? $locale : '';
             
            if (empty($locale)) {
                $this->stepExecution->addWarning("Store or Job Locale Not found", [], new \DataInvalidItem(['locale' => $locale, 'code' => $code ]));
            }
            
            $result['labels'][$locale] = $attributeSet['attribute_set_name'];
            // Add to Mapping in Database
            $externalId = !empty($attributeSet['attribute_set_id']) ? $attributeSet['attribute_set_id'] : null;
            $relatedId = !empty($attributeSet['entity_type_id']) ? $attributeSet['entity_type_id'] : null;
            $code = $result['code'];
            if ($code && $externalId) {
                $mapping = $this->addMappingByCode($code, $externalId, $relatedId, $this::AKENEO_ENTITY_NAME);
            }

            $results[] = $result;
        }

        return $results;
    }

    protected function filterAttributes($attributes)
    {
        $results = [];
        foreach ($attributes as $key => $attribute) {
            if (!empty($attribute['is_user_defined']) && isset($attribute['frontend_input']) && in_array($attribute['frontend_input'], $this->attributeTypes)) {
                $results[] = $attribute['attribute_code'];
            }
        }
        
        return $results;
    }

    /**
     * Return the all Attribute sets
     */
    private function getAttributeSets()
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getAttributeSets');
        $url = str_replace('[pageSize]=50', '[pageSize]=10000', $url);
        $method = 'GET';
        
        return $this->fetchApiByUrlAndMethod($url, $method);
    }

    private function getAttributeByAttributeSet($attributeSetId)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getAttributeSet');
        $url = str_replace('{attributeSetId}', $attributeSetId, $url);
        $method = 'GET';
        
        return $this->fetchApiByUrlAndMethod($url, $method);
    }

    private function fetchApiByUrlAndMethod($url, $method)
    {
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
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

            return  [
                'error' => $lastResponse,
                'requstURL' => $url,
                'method' => $method,
            ];
        }
    }

    protected function getAttributesByAttributeSetId($attributeSetId)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getAttributeSet');
        $url = str_replace('{attributeSetId}', $attributeSetId, $url);
        $method = 'GET';
        $attributesCode = [];
        $attributes = $this->fetchApiByUrlAndMethod($url, $method);
        
        foreach ($attributes as $key => $attribute) {
            if (!empty($attribute['attribute_code'])) {
                if (empty($attribute['is_user_defined'])) {
                    if (!in_array($attribute['attribute_code'], $this->systemAttribute)) {
                        continue;
                    }
                }
                $mapping = $this->connectorService->getAttributeByCode($attribute['attribute_code']);
                if ($mapping) {
                    if ($attribute['is_required'] === true) {
                        $this->attributeRequired[] = $attribute['attribute_code'];
                    }
                    $attributesCode[] = $attribute['attribute_code'];
                }
            }
        }
        
        return $attributesCode;
    }
}
