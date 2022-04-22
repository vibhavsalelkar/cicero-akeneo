<?php

namespace Webkul\Magento2Bundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

class BaseReader extends \AbstractReader implements
    \ItemReaderInterface,
    \InitializableInterface,
    \StepExecutionAwareInterface
{
    const MAGENTO_PRODUCT_MEDIA_DIR = '/pub/media/catalog/product/';

    const MAGENTO_CATEGORY_MEDIA_DIR = '/pub/media/catalog/category/';

    protected $jsonHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

    /** @var StepExecution */
    protected $stepExecution;

    protected $credentials;

    protected $oauthClient;

    protected $mappingRepository;

    protected $em;

    protected $storeConfigs;

    protected $defaultLocale;

    protected $connectorService;

    protected $imageFlag;

    protected $storeCode;

    /**
     * @param Magento2Connector     $connectorService
     */
    public function __construct(
        Magento2Connector $connectorService,
        \Doctrine\ORM\EntityManager $em
    ) {
        $this->connectorService = $connectorService;
        $this->em = $em;
    }

    protected function getHostName()
    {
        try {
            $result = rtrim($this->credentials['hostName'], '/');
        } catch (\Exception $e) {
            $result = null;
        }
        
        return $result;
    }

    protected function initializeApiRequirents()
    {
        $credentials = $this->getCredentials();
        
        $this->createOauthClient();
        if ($this->em) {
            $this->mappingRepository = $this->em->getRepository('Magento2Bundle:DataMapping');
        }
        
        if (isset($credentials['storeMapping']) && isset($credentials['storeMapping']['allStoreView']) && !empty($credentials['storeMapping']['allStoreView']['channel'])) {
            $this->defaultChannel = $credentials['storeMapping']['allStoreView']['channel'];
        } else {
            throw new \Exception("Default Channel Not Found, Set the Default Channel in the Store Mapping");
        }
        
        if (isset($credentials['storeMapping']) && isset($credentials['storeMapping']['allStoreView']) && !empty($credentials['storeMapping']['allStoreView']['locale']) && in_array($credentials['storeMapping']['allStoreView']['locale'], $this->connectorService->getActiveLocales())) {
            $this->defaultLocale = $credentials['storeMapping']['allStoreView']['locale'];
        } else {
            throw new \Exception("Default Locale Not Found, Set the Default Locale in the Store Mapping");
        }
    }

    protected function createOauthClient()
    {
        try {
            $params = $this->credentials;
            $oauthClient = new OAuthClient(!empty($params['authToken']) ? $params['authToken'] : null, $params['hostName']);
            $this->oauthClient = $oauthClient;
        } catch (\Exception $e) {
        }

        return $this->oauthClient;
    }

    public function getCredentials()
    {
        if (!$this->credentials) {
            $this->credentials = $this->connectorService->getCredentials();
        }

        return $this->credentials;
    }

    /**
    * {@inheritdoc}
    */
    public function setStepExecution(\StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
        if (!empty($this->connectorService) && $this->connectorService instanceof Magento2Connector) {
            $this->connectorService->setStepExecution($stepExecution);
        }
        
        $this->initializeApiRequirents();
    }

    public function getStoreMapping()
    {
        $storeMapping = !empty($this->credentials['storeMapping']) ? $this->credentials['storeMapping'] : [];
        $this->storeMapping = array_filter($storeMapping);
        return $this->storeMapping;
    }
    

    /**
     * {@inheritdoc}
     */
    protected function getResults()
    {
        return new \ArrayIterator($this->mappingRepository->findAll());
    }
    

    protected function getValues($product)
    {
        $attributeSetId = $product['attribute_set_id'];
        $result = [];
        $attributesCodes = [];
        $attributeMappings = $this->connectorService->getMergeMappings();
        $productLocalesData = [];

        //Import for all storeview locales data
        $jobLocales = $this->stepExecution->getJobParameters()->get('filters')['structure']['locales'];
        foreach ($this->storeMapping as $storeCode => $storeData) {
            if (is_array($this->locales) && in_array($storeData['locale'], $this->locales)
            && $storeData['locale'] !== $this->defaultLocale
            && !array_key_exists($storeData['locale'], array_keys($productLocalesData))) {
                $this->locale = $storeData['locale'];
                if (in_array($storeData['locale'], $jobLocales)) {
                    $productLocalesData[$storeData['locale']] = $this->getProductBySKU($product['sku'], $storeCode);
                }
            }
        }

        foreach ($attributeMappings as $magentoField => $akeneoField) {
            $value = [];
            if ($magentoField === 'quantity') {
                $magentoField = 'qty';
            }
            if ($magentoField === 'quantity_and_stock_status') {
                $magentoField = 'is_in_stock';
            }

            //Default Store Value Formate
            $this->locale = $this->defaultLocale;
            $formateValue = $this->getFormateValue($product, $magentoField, $akeneoField);
            
            if (!empty($formateValue)) {
                $value[] = $formateValue;
            }
            
            $results = $this->connectorService->getAttributeTypeLocaleAndScope($akeneoField);
            $localizable = isset($results[0]['localizable']) ? $results[0]['localizable'] : null;
            $scopable = isset($results[0]['scopable']) ? $results[0]['scopable'] : null ;

            //All Store View Data
            if (!empty($localizable) && !empty($scopable)) {
                foreach ($productLocalesData as $locale => $data) {
                    $this->locale = $locale;
                    $formateValue = $this->getFormateValue($data, $magentoField, $akeneoField);
                    
                    if (!empty($formateValue)) {
                        $value[] = $formateValue;
                    }
                }
                if (!empty($value)) {
                    $result[$akeneoField] = $value;
                }
            } else {
                if (!empty($value)) {
                    $result[$akeneoField] = $value;
                }
            }
        }
         
        //add images
        $otherMappings = $this->connectorService->getOtherMappings();
        if (!empty($otherMappings) && !empty($otherMappings['images'])) {
            $counter = 0;
            foreach ($otherMappings['images'] as $image) {
                if (!empty($product['media_gallery_entries'][$counter])) {
                    $imageArr = $product['media_gallery_entries'][$counter++];
                    if ($imageArr['media_type'] === 'image') {
                        $imgUrl = $this->credentials['hostName'] . self::MAGENTO_PRODUCT_MEDIA_DIR . $imageArr['file'];
                        $results = $this->connectorService->getAttributeTypeLocaleAndScope($image);
                        $localizable = isset($results[0]['localizable']) ? $results[0]['localizable'] : null;
                        $scopable = isset($results[0]['scopable']) ? $results[0]['scopable'] : null ;
                        $check = $this->connectorService->checkExistProduct($product['sku'], $product['type_id']);
                        $imgUrlData = $this->imageStorer($imgUrl);
                        if (!empty($imgUrlData)) {
                            if ((!$check) || $check && $this->imageFlag) {
                                $result[$image] = [
                                    array(
                                        'locale' => $localizable ? $this->defaultLocale : null,
                                        'scope' => $scopable ? $this->scope : null,
                                        'data' => $imgUrlData, //image
                                    )
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }
    

    protected function getProductBySKU($sku, $store)
    {
        /* not store-view wise */
        $url = $this->oauthClient->getApiUrlByEndpoint('getProduct', $store);
        $url = str_replace('{sku}', urlencode($sku), $url);
        $method = 'GET';
         
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
             
            $error = ['error' => $lastResponse ];
             
            return $error;
        }
    }

    protected function getCategoryDetail($id, $storeCode)
    {
        $method = 'GET';
        /* not store-view wise */
        $url = $this->oauthClient->getApiUrlByEndpoint('getCategory', $storeCode);
        
        $url = str_replace('{id}', urlencode($id), $url);
         
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
             
            $error = ['error' => $lastResponse ];
             
            return $error;
        }
    }

    protected function getFormateValue($product, $magentoField, $akeneoField)
    {
        $result = [];
        if (is_array($product)) {
            foreach ($product as $field => $value) {
                if ($magentoField === $field) {
                    $result = $this->formatedValue($magentoField, $akeneoField, $value, $this->locale, $this->scope);
                    
                    return $result;
                }

                if ($field === "extension_attributes") {
                    if (!empty($product[$field]['stock_item'])) {
                        foreach ($product[$field]['stock_item'] as $key => $value1) {
                            if ($magentoField === $key) {
                                $result = $this->formatedValue($magentoField, $akeneoField, $value1, $this->locale, $this->scope);
                                
                                return $result;
                            }
                        }
                    }
                }

                if ($field === "custom_attributes") {
                    if (!empty($product["custom_attributes"])) {
                        foreach ($product["custom_attributes"] as $custom_attribute) {
                            if (strcasecmp($magentoField, $custom_attribute['attribute_code']) === 0) {
                                $value = $custom_attribute['value'];
                                
                                $result = $this->formatedValue($magentoField, $akeneoField, $value, $this->locale ?? '', $this->scope);
                                
                                return $result;
                            }
                        }
                    }
                }
            } // end foreach
        } // end if
        
        return $result;
    }

    protected function grabImage($filePath)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $filePath);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11');
        $res = curl_exec($ch);
        $rescode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch) ;

        return ['code' =>$rescode, 'response' => $res];
    }

    protected function imageStorer($filePath)
    {
        $this->imageFlag = false;
 
        $fileName = explode('/', $filePath);
        $headers = @get_headers($filePath);
        
        if (!stripos($headers[0], "200 OK") ? true : false) {
            $filePath = str_replace('/pub', '', $filePath);
        }

        $fileName = explode('?', $fileName[count($fileName)-1])[0] ?? '';

        $localpath = $this->uploadDir."/tmpstorage/".$fileName;
        
        if (!file_exists(dirname($localpath))) {
            if (!mkdir(dirname($localpath), 0777, true)) {
                $this->stepExecution->addWarning("'Failed to create temp storage folder for images...", [], new \DataInvalidItem(["path" => $localpath]));
                return ;
            }
        }

        if (file_exists($localpath)) {
            $filesize = filesize($localpath);
            if (!($filesize > 0)) {
                $imageRes = $this->grabImage($filePath);
                if ($imageRes['code'] == '200') {
                    $check = file_put_contents($localpath, $imageRes['response']);
                    $this->imageFlag = true;
                }
            }
        } else {
            $imageRes = $this->grabImage($filePath);
            if ($imageRes['code'] == '200') {
                $check = file_put_contents($localpath, $imageRes['response']);
                $this->imageFlag = true;
            }
        }

        return $localpath;
    }


    // Formate Value according to akeneo
    public function formatedValue($magentoField, $attributeCode, $value, $locale, $scope)
    {
        $results = $this->connectorService->getAttributeTypeLocaleAndScope($attributeCode);
        $localizable = isset($results[0]['localizable']) ? $results[0]['localizable'] : null;
        $scopable = isset($results[0]['scopable']) ? $results[0]['scopable'] : null ;
        $type = isset($results[0]['type']) ? $results[0]['type'] : null ;
        $result = null;
        
        if ($type === "pim_catalog_price_collection") {
            $currency = 'USD';
            $storeMapping = $this->connectorService->getStoreMapping();
            foreach ($storeMapping as $storeCode => $storeData) {
                if ($storeData['locale'] == $locale) {
                    $currency = $storeData['currency'];
                    break;
                }
            }

            $result= array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' => [
                            array(
                                'amount' => isset($value) ? $value : 0,
                                'currency' => $currency,
                            )
                        ]
                    );
        } elseif ($type === "pim_catalog_metric") {
            $defaultUnit =  isset($results[0]['defaultMetricUnit']) ? $results[0]['defaultMetricUnit'] : null ;
            $metricFamily = isset($results[0]['metricFamily']) ? $results[0]['metricFamily'] : null ;

            if ($metricFamily === 'Weight') {
                $result= array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' => array(
                                'amount' => isset($value) ? $value : 0,
                                'unit' => $defaultUnit
                            ),
                    );
            } else {
                $value = explode(' ', $value);
                $result= array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' =>  array(
                                    'amount' => isset($value[0]) ? $value[0]  : 0,
                                    'unit' => isset($value[1]) ? $value[1]  : $defaultUnit,
                                ),
                        
                    );
            }
        } elseif (in_array($type, ['pim_catalog_multiselect', 'pim_catalog_simpleselect'])) {
            if ($type === "pim_catalog_multiselect") {
                $options = [];
                $value = explode(',', $value);
                foreach ($value as $id) {
                    $optCode = $this->connectorService->searchOptionsValueByExternalId($id, $magentoField);
                    
                    if ($optCode !== null) {
                        $options[] = $optCode;
                    }
                }
            } else {
                if ($magentoField == "is_in_stock") {
                    $magentoField = "quantity_and_stock_status";
                }
                $options = $this->connectorService->searchOptionsValueByExternalId($value, $magentoField);
            }

            if (!empty($options)) {
                $result= array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' => $options
                    );
            }
        } elseif (in_array($type, ['pim_catalog_text','pim_catalog_textarea' ])) {
            if (!empty($value)) {
                if (!is_array($value)) {
                    $value = (string)$value;
                }
                $result= array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' => isset($value) ? $value : null
                    );
            }
        } elseif (in_array($type, ['pim_catalog_boolean'])) {
            if (!is_array($value)) {
                $value = (bool)$value;
            }
            $result= array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' => isset($value) ? $value : null
                    );
        } else {
            if (!empty($value)) {
                $result= array(
                        'locale' => $localizable ? $locale : null,
                        'scope' => $scopable ? $scope : null,
                        'data' => isset($value) ? $value : null
                    );
            }
        }
        
        return $result;
    }

    protected $attributeTypes = [
        'text',
        'textarea',
        'date',
        'boolean',
        'multiselect',
        'select',
        'price'
    ];

    protected $systemAttribute = [
        'name',
        'description',
        'price',
        'meta_title',
        'meta_keyword',
        'meta_description',
        'news_from_date',
        'news_to_date',
        'visibility',
        'gift_message_available',
        'weight',
        'url_key',
        'country_of_manufacture',
        'tax_class_id',
        'status',
        'special_to_date',
        'special_price',
        'special_from_date',
        'sku',
        'short_description',
        'quantity_and_stock_status',
    ];
}
