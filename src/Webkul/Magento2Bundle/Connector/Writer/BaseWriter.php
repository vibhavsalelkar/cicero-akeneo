<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Traits\StepExecutionTrait;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\ApiEndPointsTrait;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Add products to magento2 Api
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
abstract class BaseWriter implements \StepExecutionAwareInterface, \InitializableInterface
{
    use StepExecutionTrait;
    
    use ApiEndPointsTrait;

    const DEFAULT_STORE_VIEW_CODE = 'allStoreView';

    /** @var \StepExecution */
    protected $stepExecution;

    /* @var params for connector */
    private $params;

    protected $oauthClient;

    protected $jobInstance;

    protected $em;

    protected $mappingRepository;

    protected $connectorService;

    protected $credentials;

    protected $storeViewCode;
 
    // protected $storeMapping;

    protected $storeConfigs;
 
    protected $rootCategoriesWebsiteId = [];

    protected $reservedAttributes = [
                                    'sku', 'name','weight', 'status', 'description', 'short_description', 'price', 'visibility', 'weight',
                                    'tax_class_id', 'quantity_and_stock_status', 'category_ids', 'tier_price', 'price_view', 'gift_message_available', 'website_ids'
                                ];

    /* @var default json headers for api */
    protected $jsonHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

    protected $defaultStoreView;

    protected $defaultLocale;

    protected $defaultChannel;

    protected $channelRepository;

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->defaultStoreView = $this->getDefaultStoreView();
        $this->defaultLocale = $this->defaultStoreView['locale'] ?? null;
        $this->defaultChannel = $this->defaultStoreView['channel'] ?? null;
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

        $this->initRootCategoriesWebsiteId();
    }
    
    /**
    * Initialize the root categories website ids as per mapping in setStepExecution method
    */
    protected function initRootCategoriesWebsiteId()
    {
        if (!$this->channelRepository) {
            $this->channelRepository = $this->connectorService->getChannelRepository();
        }
        $storeMappings = $this->getStoreMapping();
        
        if (is_array($storeMappings)) {
            foreach ($this->channelRepository->getFullChannels() as $channel) {
                foreach ($storeMappings as $storeViewCode => $storeMapping) {
                    // channel wise website id
                    if (isset($storeMapping['channel']) && $channel->getCode() === $storeMapping['channel'] && isset($storeMapping['website_id'])) {
                        $this->rootCategoriesWebsiteId[$channel->getCategory()->getCode()][] = $storeMapping['website_id'];
                    } else {
                        continue;
                    }
                }
            }
        }
    }
    
    public function getParameters()
    {
        if (!$this->stepExecution) {
            throw new \Exception('call setStepExecution first');
            return [];
        }
        if (!$this->params) {
            $this->params = $this->stepExecution->getJobParameters()->all();
        }

        return $this->params;
    }

    public function getStoreMapping()
    {
        $credentials = $this->getCredentials();
        $storeMapping = !empty($credentials['storeMapping']) ? $credentials['storeMapping'] : [];
       
        return array_filter($storeMapping);
    }

    public function getDefaultStoreView()
    {
        $defaultStoreView = null;
        $credentials = $this->getCredentials();
        
        $storeMapping = !empty($credentials['storeMapping']) ? $credentials['storeMapping'] : [];
        
        return  $storeMapping[self::DEFAULT_STORE_VIEW_CODE] ?? null;
    }

    public function getCredentials()
    {
        if (!$this->credentials) {
            $this->credentials = $this->connectorService->getCredentials();
        }

        return $this->credentials;
    }

    public function getStoreConfigs()
    {
        if ($this->oauthClient && !$this->storeConfigs) {
            $url = $this->oauthClient->getApiUrlByEndpoint('storeConfigs');
            $method = 'GET';
            try {
                $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
                $this->storeConfigs = json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
            }
        }

        return $this->storeConfigs;
    }

    protected function initializeApiRequirents()
    {
        $credentials = $this->getCredentials();
        $this->createOauthClient();
        if ($this->em) {
            $this->mappingRepository = $this->em->getRepository('Magento2Bundle:DataMapping');
        }

        $this->getStoreConfigs();
        
        if (isset($credentials['storeMapping']) && isset($credentials['storeMapping'][self::DEFAULT_STORE_VIEW_CODE]) && !empty($credentials['storeMapping'][self::DEFAULT_STORE_VIEW_CODE]['channel'])) {
            $this->defaultChannel = $credentials['storeMapping'][self::DEFAULT_STORE_VIEW_CODE]['channel'];
        } else {
            throw new \Exception("Default Channel Not Found, Set the Default Channel in the Store Mapping");
        }
        if (isset($credentials['storeMapping']) && isset($credentials['storeMapping'][self::DEFAULT_STORE_VIEW_CODE]) && !empty($credentials['storeMapping'][self::DEFAULT_STORE_VIEW_CODE]['locale']) && in_array($credentials['storeMapping'][self::DEFAULT_STORE_VIEW_CODE]['locale'], $this->connectorService->getActiveLocales())) {
            $this->defaultLocale = $credentials['storeMapping'][self::DEFAULT_STORE_VIEW_CODE]['locale'];
        } else {
            throw new \Exception("Default Locale Not Found, Set the Default Locale in the Store Mapping");
        }
    }
    

    protected function getJobInstance()
    {
        if (!$this->stepExecution) {
            throw new \Exception('call setStepExecution first');
            return [];
        }
        if (!$this->jobInstance) {
            $this->jobInstance = $this->stepExecution->getJobExecution()->getJobInstance();
        }

        return $this->jobInstance;
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

    /**
    * filters data from array and add data into wrapper element
    *
    * @param array $item
    * @param array $matcher
    * @param string $wrapper
    *
    * @return array $data to post to magento2 api
    */
    protected function createArrayFromDataAndMatcher($item, $matcher, $wrapper = null)
    {
        $data = [];
        foreach ($matcher as $akeneoKey => $externalKey) {
            if ($akeneoKey === 'useable_as_grid_filter2') {
                $akeneoKey = 'useable_as_grid_filter';
            }
            
            if (array_key_exists($akeneoKey, $item)) {
                $data[$externalKey] = $item[$akeneoKey];
            }
        }

        return $wrapper ? [$wrapper => $data] : $data;
    }

    /**
     * get the categories websites ids
     * @var $categories
     * @return array $websiteIds;
     */
    protected function getCategoriesWebsiteIds(array $categories): array
    {
        $websiteIds = [];
        
        foreach ($categories as $category) {
            if (array_key_exists($category, $this->rootCategoriesWebsiteId)) {
                $categoryWebsiteIds =  $this->rootCategoriesWebsiteId[$category];
                if (is_array($categoryWebsiteIds)) {
                    $websiteIds = array_merge($websiteIds, $categoryWebsiteIds);
                }
            } else {
                do {
                    $parentCategory = $this->getParentCategoryCode($category);
                    if ($parentCategory) {
                        $category = $parentCategory;
                    }
                } while (!array_key_exists($parentCategory, $this->rootCategoriesWebsiteId) && $parentCategory);

                if (array_key_exists($category, $this->rootCategoriesWebsiteId)) {
                    $categoryWebsiteIds =  $this->rootCategoriesWebsiteId[$category];
                    if (is_array($categoryWebsiteIds)) {
                        $websiteIds = array_merge($websiteIds, $categoryWebsiteIds);
                    }
                }
            }
        }
        
        return array_unique($websiteIds);
    }

    /**
     * Get the Parent Category Code
     * @var string $categoryCode
     * @return string $parentCode
     */
    protected function getParentCategoryCode(string $categoryCode):string
    {
        $parentCode = '';
        $result = $this->connectorService->getCategoryByCode($categoryCode);
        if ($result && $result->getParent()) {
            $parentCode = $result->getParent()->getCode();
        }

        return $parentCode;
    }
}
