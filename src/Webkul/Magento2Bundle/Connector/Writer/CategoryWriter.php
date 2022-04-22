<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Symfony\Component\HttpFoundation\Response;
use Webkul\Magento2Bundle\Connector\Writer\Model;
use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\ChannelAwareTrait;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\Services\CategoryImageDescription;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Add cats to magento2 Api
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class CategoryWriter extends BaseWriter implements \ItemWriterInterface, \InitializableInterface
{
    const AKENEO_ENTITY_NAME = 'category';
    const ERROR_DUPLICATE_URL_KEY = 'URL key for specified store already exists.';
    const ERROR_ENTITY_DELETED = 'No such entity with %fieldName = %fieldValue';

    use ChannelAwareTrait;
    use DataMappingTrait;

    protected $defaultStoreViewCode;

    protected $installedModule;

    protected $akeneoCategories;

    protected $parameters;

    protected $channelRepository;

    /** @var bool Checks if executed from the writer first time */
    protected $isExecuted = false;

    protected $rootCategoryCode;

    public function __construct(
        \Doctrine\ORM\EntityManager $em,
        Magento2Connector $connectorService,
        $channelRepo,
        CategoryImageDescription $categoryImage
    ) {
        $this->em = $em;
        $this->connectorService = $connectorService;
        $this->channelRepo = $channelRepo;
        $this->categoryImage = $categoryImage;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->parameters = $this->getParameters();
        $this->installedModule = $this->connectorService->getInstalledModule();
        $this->storeMapping = $this->getStoreMapping();
        $scope = $this->getChannelScope($this->stepExecution);
       
        $rootCategoryCodes = $this->getDefaultCategoryTreeCode($this->parameters);

        if (is_array($rootCategoryCodes)) {
            $this->rootCategoryCode = isset($rootCategoryCodes[0]) ? $rootCategoryCodes[0] : '';
        } else {
            $this->rootCategoryCode = $rootCategoryCodes;
        }
        
        if (!empty($this->parameters) && isset($this->parameters['isDuplicateCategoryName'])) {
            $this->akeneoCategories = $this->connectorService->getAllAkeneoCategories();
        }

        $this->isExecuted = false;
        $this->storeViewCode = 'all';
    }

    protected function validateJobLocales()
    {
        if (!in_array($this->defaultLocale, $this->parameters['filters']['structure']['locales'])) {
            $this->stepExecution->addWarning('Invalid Job', [], new \DataInvalidItem([$this->defaultLocale. ' default store view locale is not added in job']));
            $this->stepExecution->setTerminateOnly();

            return false;
        }

        return true;
    }

    /**
     * write category to magento2 Api
     * add category add translation for different store views using different request
     * @param array $items
     */
    public function write(array $items)
    {
        if (!$this->isExecuted) {
            $validate = $this->validateJobLocales();
            if (!$validate) {
                return;
            }
        }
        
        while (count($items)) {
            $errorMsg = false;
            $updateTrack = null;
            $item = array_shift($items);
            $item['code'] = strtolower($item['code']);
            $name = $item['labels'][$this->defaultLocale] ?? null;

            if ('' === $name || null === $name) {
                $name = $item['code'];
            }

            $category = null;

            if ($item['code'] === $this->rootCategoryCode) {
                $category = $this->getDefaultStoreCategory();
                if (!empty($category['id'])) {
                    $this->updateMappingByCode($item['code'], $category['id'], $category['parent_id']);
                }
            }

            $item['name'] = $name;
            $mapping = $this->getMappingByCode($item['code']);

            /* format data */
            $data = $this->createArrayFromDataAndMatcher(
                $item,
                $this->matcher,
                self::AKENEO_ENTITY_NAME
            );
            
            /* add filler attributes */
            $data[self::AKENEO_ENTITY_NAME] = array_merge(
                $data[self::AKENEO_ENTITY_NAME],
                $this->filler
            );

        
            if ($item['parent'] == $this->rootCategoryCode) {
                $category = $this->getDefaultStoreCategory();
                if ($category && empty($category['error'])) {
                    $this->updateMappingByCode($item['parent'], $category['id'], $category['parent_id']);
                }
            }

            if ($item['parent']) {
                $parentMapping = $this->getMappingByCode($item['parent']);
                if ($item['parent'] && $parentMapping) {
                    $data[self::AKENEO_ENTITY_NAME]['parent_id'] = $parentMapping->getExternalId();
                } elseif (!$item['parent']) {
                    $data[self::AKENEO_ENTITY_NAME]['parent_id'] = 1;
                } else {
                    /* no parent reference found */
                    $errorMsg = 'Parent with code: ' . $item['parent'] . ' not exported yet.';
                    $this->stepExecution->addWarning(
                        $errorMsg,
                        [],
                        new \DataInvalidItem([ 'code' => $item['code'] ])
                    );
                    continue;
                }
            } else {
                /* add to default category */
                $data[self::AKENEO_ENTITY_NAME]['parent_id'] = 1;
            }

            // ------------- add image and description category----------
            if (key_exists("CategoryExtendBundle", $this->installedModule)) {
                $custom_attr = $this->categoryImage->setCategoryAdditionalInformation($item, $this->defaultLocale);
                $data[self::AKENEO_ENTITY_NAME]['custom_attributes'] = $custom_attr;
            }

            if ($mapping) {
                /* mapping exists */
                $updateTrack = $this->connectorService->getEntityTrackByEntityAndCode(self::AKENEO_ENTITY_NAME, $item['code']);
                if (!empty($this->parameters['addNewOnly']) && !$updateTrack) {
                    $this->stepExecution->incrementSummaryInfo('already_exported');
                    continue;
                }
                
                if ($mapping->getExternalId()) {
                    $data[self::AKENEO_ENTITY_NAME]['id'] = $mapping->getExternalId();
                    $category = $this->addCategory($data, $mapping->getExternalId());
                    
                    if (!empty($category['error']['http_code']) && $category['error']['http_code'] == RESPONSE::HTTP_NOT_FOUND) {
                        $category = $this->handleDeletedEntity($item, $data, $mapping);
                    }
                }
            } else {
                /* mapping doesn't exists */
                $data[self::AKENEO_ENTITY_NAME]['include_in_menu'] = true;
                $data[self::AKENEO_ENTITY_NAME]['custom_attributes'][] = [
                    'attribute_code' => 'url_key',
                    'value'          => strtolower($this->connectorService->formatUrlKey($item['name'])),
                ];
                $category = $this->addCategory($data);

                if ($category && empty($category['error']) && isset($category['id'])) {
                    $this->addMappingByCode($item['code'], $category['id'], $category['parent_id']);
                } elseif (!empty($category['error']['parameters'][0])) {
                    $this->getCategoriesAndAddMappings();
                    $mapping = $this->getMappingByCode($item['code']);
                    
                    if (!$mapping) {
                        $this->stepExecution->addWarning(
                            $category['error']['parameters'][0],
                            [],
                            new \DataInvalidItem(['code' => $item['code'], 'debugLine' => __LINE__ ])
                        );
                    }
                }
            }
            
            if (!empty($updateTrack)) {
                $this->connectorService->removeTrack($updateTrack);
            }
            if (!empty($updateTrack)) {
                $this->connectorService->removeTrack($updateTrack);
            }

            if (!empty($category)) {
                $this->addCategoryTranslations($category, $item, $this->storeMapping, $data);
            }
 
            if (!$errorMsg && isset($category['id'])) {
                /* increment write count */
                $this->stepExecution->incrementSummaryInfo('write');
            }
        }
    }

    /**
    * when resource is deleted from magento, recrete category and add translations
    *
    * @param array $item 'Base item''
    * @param array $data 'formatted data'
    * @param DataMapping $mapping 'existing mapping'
    * @return array $category 'recreated category'
    */
    protected function handleDeletedEntity($item, $data, $mapping)
    {
        unset($data[self::AKENEO_ENTITY_NAME]['id']);
        unset($data[self::AKENEO_ENTITY_NAME]['parent_id']);
        $this->em->remove($mapping);
        $this->em->flush();
        $parentMapping = $this->getMappingByCode($item['parent']);
        if ($parentMapping) {
            $data[self::AKENEO_ENTITY_NAME]['parent_id'] = $parentMapping->getExternalId();
        } elseif (!$item['parent']) {
            $data[self::AKENEO_ENTITY_NAME]['parent_id'] = 1;
        }
        $category = $this->addCategory($data);

        if (!empty($category)) {
            $this->addCategoryTranslations($category, $item, $this->storeMapping, $data);
        }
        
        if ($category && empty($category['error'])) {
            $this->addMappingByCode($item['code'], $category['id'], $category['parent_id']);
        }

        return $category;
    }

    /* fetch default store categroy from api */
    protected function getDefaultStoreCategory()
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('categories') ;
        $url = strstr($url, '?', true) . '?depth=0';
        $method = 'GET';

        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);

            return $results;
        } catch (\Exception $e) {
            $lastResponse = json_decode($this->oauthClient->getLastResponse(), true);
            $error = ['error' => $lastResponse ];

            return $error;
        }
    }

    /* add category according to current store view */
    protected function addCategory(array $category, $categoryId = null)
    {
        $storeViewCode = $this->storeViewCode;
        $url = $this->oauthClient->getApiUrlByEndpoint('categories', $storeViewCode);
        $method = 'POST';
        if ($categoryId) {
            $url = $this->oauthClient->getApiUrlByEndpoint('updateCategory', $storeViewCode);
            $url = str_replace('{category}', $categoryId, $url);
            $method = 'PUT';
        }
        
        try {
            $this->oauthClient->fetch($url, json_encode($category), $method, $this->jsonHeaders);
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

    /* add translations of category store view wise */
    protected function addCategoryTranslations($category, $item, $storeMappings, $data)
    {
        if ($category && empty($category['error'])) {
            foreach ($storeMappings as $storeViewCode => $storeMapping) {
                if ($storeViewCode == 'allStoreView') {
                    continue;
                }
                if (!empty($storeMapping['locale']) && in_array($storeMapping['locale'], $this->parameters['filters']['structure']['locales'])) {
                    $locale = $storeMapping['locale'];
                    
                    // if ($locale !== $this->defaultLocale && !empty($item['labels'][$locale])) {
                    $this->storeViewCode = $storeViewCode;
                    $data[self::AKENEO_ENTITY_NAME]['name'] = isset($item['labels'][$locale]) ?$item['labels'][$locale] : '';
                    $mapping = $this->getMappingByCode($item['code']);
                    if (key_exists("CategoryExtendBundle", $this->installedModule)) {
                        $custom_attr = $this->categoryImage->setCategoryAdditionalInformation($item, $locale);
                        $data[self::AKENEO_ENTITY_NAME]['custom_attributes'] = $custom_attr;
                    }
                    if ($mapping) {
                        if ($mapping->getExternalId()) {
                            $data[self::AKENEO_ENTITY_NAME]['id'] = $mapping->getExternalId();
                            $data[self::AKENEO_ENTITY_NAME]['parent_id'] = $mapping->getRelatedId();
                            $category = $this->addCategory($data, $mapping->getExternalId());
                        }
                    } else {
                        $data[self::AKENEO_ENTITY_NAME]['include_in_menu'] = true;
                        if ($item['parent']) {
                            $parentMapping = $this->getMappingByCode($item['parent']);
                            if ($parentMapping) {
                                $data[self::AKENEO_ENTITY_NAME]['parent_id'] = $parentMapping->getExternalId();
                            } elseif (!$item['parent']) {
                                $data[self::AKENEO_ENTITY_NAME]['parent_id'] = 1;
                            } else {
                                /* no reference found */
                            }
                        } else {
                            /* add to default category */
                            $data[self::AKENEO_ENTITY_NAME]['parent_id'] = 0;
                        }

                        $data[self::AKENEO_ENTITY_NAME]['custom_attributes'][] = [
                                'attribute_code' => 'url_key',
                                'value'          => $this->connectorService->formatUrlKey($data[self::AKENEO_ENTITY_NAME]['name']),
                            ];
                        $category = $this->addCategory($data, $category['id']);
                        if ($category && empty($category['error'])) {
                            $this->addMappingByCode($item['code'], $category['id'], $category['parent_id']);
                        }
                    }
                    // }
                }
            }
        }
    }

    protected function getCategoriesAndAddMappings()
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('categories');
        $method = 'GET';
        
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $results = [];
        }
        
        $this->addCategoryMappingByChildrenData([$results]);
    }

    private function addCategoryMappingByChildrenData($resource)
    {
        if (!empty($resource)) {
            foreach ($resource as $result) {
                $mappingCode = $this->connectorService->convertToCode($result['name']);
                if (!empty($this->parameters) && isset($this->parameters['isDuplicateCategoryName'])) {
                    $name = strtolower($this->connectorService->formatUrlKey($result['name']));
                    if (array_key_exists($name, $this->akeneoCategories)) {
                        $mappingCode = $this->akeneoCategories[$name];
                    }
                }
                $this->updateMappingByCode(
                    $mappingCode,
                    $result['id'],
                    $result['parent_id']
                );
                if (!empty($result['children_data'])) {
                    /* recursive call */
                    $this->addCategoryMappingByChildrenData($result['children_data']);
                }
            }
        }
    }

    protected $matcher = [
        'name'   => 'name'
    ];

    protected $filler = [
        'is_active' => 1
    ];
}
