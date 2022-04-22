<?php

namespace Webkul\Magento2Bundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\Connector\Reader\Import\BaseReader;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * import attributes' option from Magento 2
 *
 * @author    webkul <support@webkul.com>
 * @copyright 2010-18 Webkul (http://store.webkul.com/license.html)
 */
class AttributeOptionReader extends BaseReader implements \ItemReaderInterface, \StepExecutionAwareInterface, \InitializableInterface
{
    use DataMappingTrait;

    protected $locale;

    protected $jsonHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

    protected $itemIterator;

    protected $storeMapping;
    
    protected $firstRead;

    protected $items;

    const AKENEO_ENTITY_NAME = 'option';

    protected $optionCodes;

    protected $counter;

    protected $locales;

    protected $duplicateOptions;
    
    public function initialize()
    {
        $this->optionCodes = [];
        $this->duplicateOptions = [];
        $credentials = $this->connectorService->getCredentials();

        if (!$this->oauthClient) {
            $this->oauthClient = new OAuthClient($credentials['authToken'], $credentials['hostName']);
        }
        
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $this->locales = !empty($filters['structure']['locales']) ? $filters['structure']['locales'] : [];
        $rawParams = $this->stepExecution->getJobExecution()->getJobInstance()->getRawParameters();
        $this->storeMapping = $this->connectorService->getStoreMapping();
        $this->attributes = !empty($rawParams['selectTypeAttributes']) ? array_values(array_unique($rawParams['selectTypeAttributes'])) : [];
        $this->totalAttribute = count($this->attributes);
        $items = [];
        $this->counter = 0;

        if (!empty($this->totalAttribute)) {
            $items = $this->getPageWiseAttributeOptions($this->counter);
        }
        
        $this->items = $items;
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
            $this->counter++;
            while ($this->counter < $this->totalAttribute) {
                $items = $this->getPageWiseAttributeOptions($this->counter);
                $this->itemIterator = new \ArrayIterator($items);
                $item = $this->itemIterator->current();
                if ($item !== null) {
                    $this->stepExecution->incrementSummaryInfo('read');
                    $this->itemIterator->next();
                    break;
                } else {
                    $this->counter++;
                }
            }
        }
        if (null === $item && !empty($this->duplicateOptions)) {
            $this->stepExecution->incrementSummaryInfo('read', count($this->duplicateOptions));
            $this->stepExecution->incrementSummaryInfo('skip', count($this->duplicateOptions));
            $this->stepExecution->addWarning(
                sprintf("%s", "Duplicate Options Found, Delete them from magento, and re-run this job, To prevent export issues"),
                [],
                new \DataInvalidItem(
                    $this->duplicateOptions
                )
            );
        }

        return $item;
    }

    protected function getAttributeOptions($attributeCode, $store = null)
    {
        $results = [];
        $url = $this->oauthClient->getApiUrlByEndpoint('attributeOption', $store);
        $url = str_replace('{attributeCode}', $attributeCode, $url);
        $method = 'GET';
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $lastResponse = json_decode($this->oauthClient->getLastResponse(), true);
            $message = !empty($lastResponse['message']) ? $lastResponse['message'] : "Error! can't get attribute options";
            $this->stepExecution->addWarning($message, [], new \DataInvalidItem(['response' => json_encode($lastResponse, true) , 'attribute Code' => $attributeCode, 'store' => $store ]));

            $results = [];
        }
        
        return $results;
    }

    protected function formatOptionsData($optionsStoreWise, $attributeCode)
    {
        $results = [];
        
        // $localWiseOptions = [];
        
        foreach ($optionsStoreWise as $storeLocale => $options) {
            foreach ($options as $option) {
                if (isset($option['value']) && !empty($option['label'])) {
                    $localWiseOptions[$option['value']][$storeLocale] = $option['label'];
                }
            }
        }
        $sort_order = 0;

        foreach ($localWiseOptions as $optionCode => $option) {
            if ($optionCode != 0
                && empty($optionCode)
                && !isset($option['code'])) {
                continue;
            }

            $code = $this->connectorService->convertToCode($option['code']) ;
            
            $mappingCode = !empty($code) ? $code . '('. $attributeCode .')' : null;

            //Check the duplicate options
            if (in_array($mappingCode, $this->optionCodes) && !empty($code)) {
                $externalId = $optionCode;
                $relatedId = $attributeCode;
                if ($code && $externalId !== null) {
                    $mapping = $this->addMappingByExternalId($mappingCode, $externalId, $relatedId, $this::AKENEO_ENTITY_NAME);
                }

                if (isset($this->duplicateOptions[$code])) {
                    continue;
                }

                $this->duplicateOptions[$code][] = 'attribute option code: ' . $code . ' || attribute code: ' . $attributeCode;
                
                continue;
            } else {
                $this->optionCodes[] = $mappingCode;
            }

            $remoteLables = array_values($option);
          
            $mapping = $this->connectorService->attributeOptionCheckInDB($code, $attributeCode, $remoteLables);

            if (!is_null($mapping)) {
                //Already exist mapping in db
                $externalId = $optionCode;
                $relatedId = $mapping['attributeId'];
                $code = $mapping['code'];
                $mappingCode = !empty($code) ? $code . '('. $attributeCode .')' : null;
            }

            $result = [
                'code'          => $code,
                'attribute'     => $attributeCode,
                'sort_order'    => ++$sort_order,
            ];
            
            foreach ($option as $optionLocale => $optionLabel) {
                if (in_array($optionLocale, $this->locales)) {
                    $result['labels'][$optionLocale] = $optionLabel;
                }
            }
            
            // Add to Mapping in Database
            $externalId = $optionCode;
            $relatedId = $attributeCode;

            if ($mappingCode && $externalId !== null) {
                $mapping = $this->addMappingByExternalId($mappingCode, $externalId, $relatedId, $this::AKENEO_ENTITY_NAME);
            }
                        
            $results[] = $result;
        }
        
        return $results;
    }



    protected $attributeTypes = [
        'text'          => 'pim_catalog_text',
        'textarea'      => 'pim_catalog_textarea',
        'date'          => 'pim_catalog_date',
        'boolean'       => 'pim_catalog_boolean',
        'multiselect'   => 'pim_catalog_multiselect',
        'select'        => 'pim_catalog_simpleselect',
        'price'         => 'pim_catalog_price_collection',
    ];

    protected function getPageWiseAttributeOptions($counter)
    {
        $items = [];
        $options = [];
        $attributeCode = !empty($this->attributes[$counter]) ? $this->attributes[$counter] : '';
        $localeExist  = [];

        if (!empty($attributeCode)) {
            // store wise options
            foreach ($this->storeMapping as $storeCode => $storeMappedData) {
                if (!empty($storeMappedData['locale'])
                    && is_array($this->locales)
                    && in_array($storeMappedData['locale'], $this->locales)
                    && !in_array($storeMappedData['locale'], $localeExist)
                ) {
                    $localeExist [] =  $storeMappedData['locale'];
                    if ($storeCode == 'allStoreView') {
                        $storeCode = 'all';
                    }
                    $attributeOptions = $this->getAttributeOptions($attributeCode, $storeCode);

                    foreach ($attributeOptions as $index => $attributeOptionValue) {
                        if (empty($attributeOptionValue['label'])) {
                            unset($attributeOptions[$index]);
                        }
                    }
                    if (!empty($attributeOptions)) {
                        $options[$storeMappedData['locale']] = $attributeOptions;
                    }
                }
            }
            
            if (!empty($options)) {
                //for option code
                $options['code'] = $this->getAttributeOptions($attributeCode, 'all');
                // Add option in according to attribute mapping akeneo attributes
                $attributeMappings = $this->connectorService->getMergeMappings();

                if (isset($attributeMappings[$attributeCode])) {
                    $attributeCode = $attributeMappings[$attributeCode];
                }
                $isAttributeExist = $this->connectorService->getAttributeByCode($attributeCode);
                if (empty($isAttributeExist)) {
                    return [];
                }
                $formattedItems = $this->formatOptionsData($options, $attributeCode);
                
                foreach ($formattedItems as $formattedItem) {
                    if (!empty($formattedItem['code'])) {
                        $items[] = $formattedItem;
                    }
                }
            }
        }
        
        return $items;
    }
}
