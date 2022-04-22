<?php

namespace Webkul\Magento2Bundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Connector\Reader\Import\BaseReader;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * import category reader from Magento 2
 *
 * @author    webkul <support@webkul.com>
 * @copyright 2010-18 Webkul (http://store.webkul.com/license.html)
 */
class CategoryReader extends BaseReader implements \ItemReaderInterface, \StepExecutionAwareInterface, \InitializableInterface
{
    use DataMappingTrait;

    const AKENEO_ENTITY_NAME = 'category';

    protected $rootCategoryCode;

    protected $parentCodes;

    protected $locale;

    protected $jsonHeaders = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

    protected $itemIterator;

    protected $storeMapping;

    protected $categories;

    protected $firstRead;

    protected $categoryCode;

    protected $installedModule;

    protected $uploadDir;

    protected $existingLocales = [];

    /** @var \FileStorerInterface */
    protected $storer;

    /** @var \FileInfoRepositoryInterface */
    protected $fileInfoRepository;

    protected $initRead;

    public function __construct(
        Magento2Connector $connectorService,
        \Doctrine\ORM\EntityManager $em,
        $uploadDir,
        \FileStorerInterface $storer,
        \FileInfoRepositoryInterface $fileInfoRepository
    ) {
        parent::__construct($connectorService, $em);
        $this->uploadDir = $uploadDir->getTempStoragePath();
        $this->storer =  $storer;
        $this->fileInfoRepository = $fileInfoRepository;
    }
    

    public function initialize()
    {
        if (!$this->initRead) {
            $this->installedModule = $this->connectorService->getInstalledModule();
            $this->credentials = $this->connectorService->getCredentials();
    
            if (!$this->oauthClient) {
                $this->oauthClient = new OAuthClient($this->credentials['authToken'], $this->credentials['hostName']);
            }
            
            $this->parentCodes = [];
            $filters = $this->stepExecution->getJobParameters()->get('filters');
            $channelCode = !empty($filters['structure']['scope']) ? $filters['structure']['scope'] : '';
            $channel = $channelCode ? $this->connectorService->findChannelByIdentifier($channelCode) : null;
            $this->locales = !empty($filters['structure']['locales']) ? $filters['structure']['locales'] : [];
            if (!in_array($this->defaultLocale, $this->locales)) {
                $this->stepExecution->addWarning('Invalid Job', [], new \DataInvalidItem([$this->defaultLocale. ' default store view locale is not added in job']));
                $this->stepExecution->setTerminateOnly();
            } else {
                $this->storeMapping = $this->connectorService->getStoreMapping();
                $items = [];
                $counter = 1;
                $this->categoryCode = [];
            }
            
            foreach ($this->storeMapping as $storeCode => $storeData) {
                if (!empty($storeData['locale']) && is_array($this->locales) && in_array($storeData['locale'], $this->locales)) {
                    if ($storeCode == 'allStoreView') {
                        $storeCode = 'all';
                    }
                    $categories = $this->getCategories($storeCode);
                    
                    if (!empty($categories)) {
                        $items[] = $this->formatData($categories, null, $storeCode, $storeData['locale']);
                    }
                } else {
                    continue;
                }
            }
            $this->categories = $this->mergeCategoriesLocales($items);
    
            $this->categoryCode = [];
            $this->firstRead = false;
            $this->initRead = true;
        }
    }
    /**
     * {@inheritdoc}
     */
    public function read()
    {
        if ($this->itemIterator === null && $this->firstRead === false) {
            $this->itemIterator = new \ArrayIterator($this->categories);
            $this->firstRead = true;
        }
        
        $item = $this->itemIterator->current();
        if ($item !== null && !empty($item['code'])) {
            if (!in_array($item['code'], $this->categoryCode)) {
                $this->stepExecution->incrementSummaryInfo('read');
                $this->itemIterator->next();
                $this->categoryCode[] = $item['code'];
            } else {
                $this->stepExecution->incrementSummaryInfo('read_loacle');
                $this->itemIterator->next();
            }
        }
        
        return  $item;
    }


    protected function getCategories($store)
    {
        /* not store-view wise */
        $url = $this->oauthClient->getApiUrlByEndpoint('categories', $store);
        
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
    
    protected function formatData($category, $parentCode = null, $storeCode, $locale, $change = false)
    {
        $result = [];
        if (empty($category['id']) || 1 == $category['id']) {
            return $result;
        }
       
        $tmpCode = $this->connectorService->findCodeByExternalId($category['id'], $this::AKENEO_ENTITY_NAME) ? : $this->connectorService->convertToCode($category['name'] . '_' . $category['id']);
        $code = $this->connectorService->matchCategoryCodeInDB($tmpCode) ? : $tmpCode;
        if (!$tmpCode) {
            return $result;
            // $existingMapping = $this->getMappingByExternalId($category['id'], self::AKENEO_ENTITY_NAME, $category['parent_id']);
        }

        if ($parentCode) {
            $code = $this->connectorService->matchRootCategoryCodeInDbByLabel($category['name'], $parentCode) ? : $code;
            $result[] = $this->preparedData($category, $code, $storeCode, $locale, $parentCode);
        } else {
            $code = $this->connectorService->matchRootCategoryCodeInDbByLabel($category['name']) ? : $code;
            $result[] = $this->preparedData($category, $code, $storeCode, $locale);
        }
       
        // Add to Mapping in Database
        $externalId = !empty($category['id']) ? $category['id'] : null;
        $relatedId = !empty($category['parent_id']) ? $category['parent_id'] : null;
        if ($code && $externalId && isset($category['name'])) {
            $mapping = $this->addMappingByExternalId($code, $externalId, $relatedId, $this::AKENEO_ENTITY_NAME, null, $category['name']);
        }

        if (!empty($category['children_data'])) {
            foreach ($category['children_data'] as $subCategory) {
                $result = array_merge($result, $this->formatData($subCategory, $code, $storeCode, $locale, $change));
            }
        }

        return $result;
    }

    protected function preparedData($category, $code, $storeCode, $locale, $parentCode = null)
    {
        $data = [
            'code'      => $code,
            'labels'    => [ $locale => $category['name'] ],
        ];

        if (key_exists("CategoryExtendBundle", $this->installedModule)) {
            $this->addImageDescription($category, $storeCode, $locale, $data);
        }

        if (isset($parentCode)) {
            $data['parent'] = $parentCode;
        }


        return $data;
    }
    
    protected function mergeCategoriesLocales($categoriesStoreWise)
    {
        $categories = [];
        foreach ($categoriesStoreWise as $categoryTree) {
            $categories = array_merge($categories, $categoryTree);
        }

        return $categories;
    }
    
    protected function addImageDescription($category, $storeCode, $locale, &$data)
    {
        $categoryDetail = $this->getCategoryDetail($category['id'], $storeCode);

        if (isset($categoryDetail['error']) || ! isset($categoryDetail['custom_attributes'])) {
            return;
        }
        
        if (false !== $descriptionIndex = array_search('description', array_column($categoryDetail['custom_attributes'], 'attribute_code'))) {
            $data['description'][$locale] = $categoryDetail['custom_attributes'][$descriptionIndex]['value'] ?? '';
        }

        if (isset($this->defaultLocale) && $this->defaultLocale === $locale) {
            if (false !== $imageIndex = array_search('image', array_column($categoryDetail['custom_attributes'], 'attribute_code'))) {
                $imagename = $categoryDetail['custom_attributes'][$imageIndex]['value'] ?? '';
                
                if (!empty($imagename)) {
                    $imgUrl = $this->credentials['hostName'] . self::MAGENTO_CATEGORY_MEDIA_DIR . $imagename;
                    $localUrl = isset($imgUrl) ? $this->imageStorer($imgUrl) : "";

                    if (null === $file = $this->fileInfoRepository->findOneByIdentifier($localUrl)) {
                        $rawFile = new \SplFileInfo($localUrl);
                        $file = $this->storer->store($rawFile, \FileStorage::CATALOG_STORAGE_ALIAS);
                        $data['image'] = $file->getKey();
                        $data['imagename'] = isset($imagename) ? $imagename : "";
                    }
                }
            }
        }
    }
}
