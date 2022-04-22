<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;

/**
 * Add products to magento2 Api
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class ProductQueueWriter extends ProductWriter
{
    protected $tempDataManager;
    protected $productModelsSkus = [];
    const AKENEO_ENTITY_NAME = 'product';

    public function __construct(\Doctrine\ORM\EntityManager $em, Magento2Connector $connectorService, $attributeRepo, $familyVariantRepo, $tempDataManager)
    {
        $this->em = $em;
        $this->connectorService = $connectorService;
        $this->attributeRepo = $attributeRepo;
        $this->familyVariantRepo = $familyVariantRepo;
        $this->tempDataManager = $tempDataManager;
    }

    /**
     * write products to magento2 rabbitMQ
     */
    public function write(array $items)
    {
        $jobExecution = $this->stepExecution->getJobExecution();
        if (!$this->oauthClient) {
            $this->stepExecution->addWarning('invalid oauth client', [], new \DataInvalidItem());
            return;
        }

        $parameters = $this->getParameters();
        $storeMappings = $this->getStoreMapping();
        $storeSettings = $this->connectorService->getOtherSettings();
        $this->channel = $this->getChannelScope($this->stepExecution);

        $this->otherSettings = $this->connectorService->getSettings();

        foreach ($items as $key => $mainItem) {
            $iteration = 0;
            $addedProductModels = [];
            foreach ($storeMappings as $storeViewCode => $storeMapping) {
                $this->locale = $locale = $storeMapping['locale'];
                $this->storeViewCode = $storeViewCode;
                $baseCurrency = !empty($storeSettings[$this->storeViewCode]['base_currency_code']) ? $storeSettings[$this->storeViewCode]['base_currency_code'] : null;
                $this->storeViewCurrency = !empty($storeMapping['currency']) ? $storeMapping['currency'] : $baseCurrency;
                $this->weightUnit = !empty($storeSettings[$this->storeViewCode]['weight_unit']) ? $storeSettings[$this->storeViewCode]['weight_unit'] : null;

                $item = $this->formatData($mainItem);
                if (!$this->defaultStoreViewCode && $iteration === 0) {
                    $this->defaultStoreViewCode = $storeViewCode;
                }

                switch ($item[PropertiesNormalizer::FIELD_MAGENTO_PRODUCT_TYPE]) {
                    case 'simple':
                        $product = [self::AKENEO_ENTITY_NAME => $item];
                        $this->addProduct($product);
                        $this->stepExecution->incrementSummaryInfo('added_to_queue');
                        break;

                    case 'variant':
                        $item[PropertiesNormalizer::FIELD_MAGENTO_PRODUCT_TYPE] = PropertiesNormalizer::SIMPLE_TYPE;
                        $parent = $item['parent'];
                        unset($item['parent']);

                        /* buffer model data */
                        if (!in_array($parent['sku'], $this->productModelsSkus)) {
                            $this->bufferProductModelData([self::AKENEO_ENTITY_NAME => $parent]);
                            $addedProductModels[] = $parent['sku'];
                        }

                        /* add link data */
                       if (!isset($jobExecution->productsModelLinks[$parent['sku']])) {
                           $jobExecution->productsModelLinks[$parent['sku']] = [];
                       }
                        $jobExecution->productsModelLinks[$parent['sku']] = array_merge($jobExecution->productsModelLinks[$parent['sku']], [$item['sku']]);

                        $this->addProduct([self::AKENEO_ENTITY_NAME => $item]);   /* child */
                        $this->stepExecution->incrementSummaryInfo('added_to_queue');
                        break;
                                        
                    case 'downloadable':
                    case 'bundle':
                    case 'grouped':
                        break;
                }
            }

            $this->productModelsSkus = array_merge($this->productModelsSkus, $addedProductModels);
        }
    }

    protected function addProduct($productData)
    {
        if (!empty($productData)) {
            /* similar data in all store view */
            if ($this->storeViewCode === $this->defaultStoreViewCode) {
                $baseData = $this->checkProductAndModifyData($productData, 'all');
                if (!empty($baseData)) {
                    $productAddUrl = $this->oauthClient->getApiUrlByEndpoint('addProductQueue', 'all');
                    try {
                        $this->oauthClient->fetch($productAddUrl, is_array($baseData) ? json_encode($baseData) : $baseData, 'POST', $this->jsonHeaders);
                    } catch (\Exception $e) {
                        $info = $this->oauthClient->getLastResponseInfo();
                        if (isset($info['http_code']) && $info['http_code'] == 404) {
                            throw new \Exception('RabbitMQ Module not found on magento2 server.');
                        }
                    }
                }
            }
            $productData = $this->checkProductAndModifyData($productData, $this->storeViewCode);
            $productAddUrl = $this->oauthClient->getApiUrlByEndpoint('addProductQueue', $this->storeViewCode);

            try {
                $this->oauthClient->fetch($productAddUrl, is_array($productData) ? json_encode($productData) : $productData, 'POST', $this->jsonHeaders);
                /* log success */
                return json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
            }
        }
    }

    protected function bufferProductModelData($productData)
    {
        if (!empty($productData)) {
            if ($this->storeViewCode === $this->defaultStoreViewCode) {
                $productAddUrl = $this->oauthClient->getApiUrlByEndpoint('addProductQueue', 'all');
                $productData = $this->checkProductAndModifyData($productData, 'all');
                $this->bufferApiRequest($productAddUrl, $productData);
            }

            $productAddUrl = $this->oauthClient->getApiUrlByEndpoint('addProductQueue', $this->storeViewCode);
            $productData = $this->checkProductAndModifyData($productData, $this->storeViewCode);
            $this->bufferApiRequest($productAddUrl, $productData);
        }
    }

    protected function bufferApiRequest($url, $data)
    {
        $workingDirectory = $this->stepExecution->getJobExecution()->getExecutionContext()
            ->get(\JobInterface::WORKING_DIRECTORY_PARAMETER);

        $this->tempDataManager->addRow(
            [
                                        'url' => $url, 'data' => json_encode($data)
                                      ],
            $workingDirectory
        );
    }

    protected function checkProductAndModifyData($productData, $storeViewCode)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getProduct', $storeViewCode);
        $url = str_replace('{sku}', urlencode($productData[self::AKENEO_ENTITY_NAME]['sku']), $url);
        
        /* fetch product */
        try {
            $this->oauthClient->fetch($url, null, 'GET', $this->jsonHeaders);
            $response = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $response = [];
        }

        if ($productData[self::AKENEO_ENTITY_NAME]['type_id'] === 'configurable' && !empty($response['extension_attributes']['configurable_product_links'])) {
            if (empty($productData[self::AKENEO_ENTITY_NAME]['extension_attributes'])) {
                $productData[self::AKENEO_ENTITY_NAME]['extension_attributes'] = [];
            }
            $productData[self::AKENEO_ENTITY_NAME]['extension_attributes']['configurable_product_links'] =  $response['extension_attributes']['configurable_product_links'];
        }

        $existingImages = [];
        $attributeMappings = $this->connectorService->getAttributeMappings();

        if (!empty($response['media_gallery_entries'])) {
            if (empty($productData[self::AKENEO_ENTITY_NAME]['custom_attributes'])) {
                $productData[self::AKENEO_ENTITY_NAME]['custom_attributes'] = [];
            }
            foreach ($response['media_gallery_entries'] as $image) {
                if (isset($image['file'])) {
                    $existingImages[] = $image['file'];
                    foreach (['image', 'small_image', 'thumbnail'] as $field) {
                        if (in_array($field, $image['types']) && !in_array($field, array_keys($attributeMappings))) {
                            $productData[self::AKENEO_ENTITY_NAME]['custom_attributes'][] = [
                                'attribute_code' => $field,
                                'value'         =>  $image['file'],
                            ];
                        }
                    }
                }
            }
        }

        if (!empty($existingImages)) {
            $mediaEntries = $productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries'] ?? [];
            foreach ($mediaEntries as $key => $mediaEntry) {
                if (isset($mediaEntry['content']['name'])) {
                    $nameArray = explode('.', $mediaEntry['content']['name']);
                    $name = reset($nameArray);

                    if (count(preg_grep('#' . $name . '#i', $existingImages))) {
                        unset($mediaEntries[$key]);
                    }
                }
            }

            $mediaEntries = array_values($mediaEntries);
            if (empty($mediaEntries)) {
                unset($productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries']);
            } else {
                if (count($mediaEntries) === count($productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries'])) {
                    $productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries'] = $mediaEntries;
                }
            }
        }

        if (!empty($productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries'])) {
            foreach ($productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries'] as $key => $mediaEntry) {
                $productData[self::AKENEO_ENTITY_NAME]['media_gallery_entries'][$key]['content']['base64_encoded_data'] = base64_encode($this->connectorService->getImageContentByPath($mediaEntry['content']['base64_encoded_data']));
            }
        }

        return $productData;
    }
}
