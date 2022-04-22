<?php

namespace Webkul\Magento2Bundle\Connector\Reader\Import;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Connector\Reader\Import\BaseProductReader;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;

/**
 * import products_model from Magento 2
 *
 * @author    webkul <support@webkul.com>
 * @copyright 2010-18 Webkul (http://store.webkul.com/license.html)
 */
class ProductModelReader extends BaseProductReader
{
    const PRODUCT_TYPE = 'configurable';
    
    const AKENEO_ENTITY_NAME = 'product';

    protected $attributes = [];

    protected $variantAttributes = [
        'quantity',
        'weight',
        'quantity_and_stock_status',
        'weight',
        'price',
        'meta_title',
        'meta_keyword',
        'meta_description',
    ];
    
    protected function getProducts($currentPage)
    {        
        $url = $this->oauthClient->getApiUrlByEndpoint('product', $this->storeCode);
        $url = str_replace('{sku}', '', $url);
        $url = strstr($url, '?', true) . '?fields=items[sku],total_count&searchCriteria[filterGroups][0][filters][0][field]=type_id&searchCriteria[filterGroups][0][filters][0][value]=configurable&searchCriteria[pageSize]='.self::PAGE_SIZE.'&searchCriteria[currentPage]=' . $currentPage;
        $method = 'GET';

        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            
            if (!empty($results['total_count']) || !empty($this->totalProducts)) {
                if (empty($this->totalProducts)) {
                    $this->totalProducts = $results['total_count'];
                }

                if ($currentPage * self::PAGE_SIZE <= $this->totalProducts) {
                    return $results;
                } else {
                    $restPage = (($currentPage * self::PAGE_SIZE) - $this->totalProducts);
                    
                    if ($restPage <  self::PAGE_SIZE) {
                        return $results;
                    } else {
                        return [];
                    }
                }
            }
        } catch (\Exception $e) {
            $lastResponse = json_decode($this->oauthClient->getLastResponse(), true);
            $responseInfo = $this->oauthClient->getLastResponseInfo();
            foreach (array_keys($responseInfo) as $key) {
                if (trim($key) == 'http_code') {
                    $lastResponse['http_code'] = $responseInfo[$key];
                    break;
                }
            }
            $error = ['error' => $lastResponse];
            $this->stepExecution->addWarning('Error: to fetch products from API ', [], new \DataInvalidItem(["API request URl" => $url, "error" => $error, "debug_line" => __LINE__]));
            
            return $error;
        }
        
        return [];
    }

    protected function formatData($productModels)
    {
        $results = [];
        foreach ($productModels as $index => $productModel) {
            if (empty($productModel['sku'])) {
                continue;
            }
            $productSKU = $productModel['sku'];

            //  Check if only new products import setting is enabled the check products is exist in Akeneo or Not
            if ($this->onlyNewProducts) {
                if ($this->connectorService->checkExistProduct($productSKU, self::PRODUCT_TYPE)) {
                    $this->stepExecution->incrementSummaryInfo('read');
                    $this->stepExecution->incrementSummaryInfo('already_exist');
                    continue;
                }
            }

            $productModel = $this->getProductBySKU($productSKU, $this->storeCode);
            
            if (isset($productModel['error'])) {
                $this->stepExecution->addWarning("Error: to fetch product from API,  SKU :  $productSKU ", [], new \DataInvalidItem(['SKU' => $productSKU, "error" => $productModel]));
                continue;
            }

            if (!empty($productModel['type_id']) && $productModel['type_id'] !== self::PRODUCT_TYPE) {
                continue;
            }
            
            $familyVariant = $this->connectorService->findFamilyVariantCode($productSKU) ? : $this->getFamilyVariant($productModel);
            
            if ($familyVariant) {
                $externalId = $productModel['id'] ?? null ;
                $mapping = $this->getMappingByExternalId($externalId);
                $result = [
                    'code'           => !empty($mapping) ? $mapping->getCode() : $productSKU,
                    'categories'     => !empty($productModel['custom_attributes']) ? $this->getCategories($productModel['custom_attributes']) : [],
                    'family_variant' => $familyVariant,
                    'values'         => $this->getValues($productModel),
                ];
            } else {
                $this->stepExecution->incrementSummaryInfo('read');
                $this->stepExecution->incrementSummaryInfo('skip');
                $this->stepExecution->addWarning("configurable product options not found in product :  $productSKU", [], new \DataInvalidItem(['SKU' => $productSKU]));
                continue;
            }
            
            //mapping configrable product variant links in database
            $this->addProductLinks($productModel);
            
            // Add to Mapping in Database
            $externalId = !empty($productModel['id']) ? $productModel['id'] : null;
            $relatedId = $familyVariant;
            $code = $result['code'] ?? '';
            if ($code && $productSKU && $externalId) {
                $this->addMappingByExternalId($code, $externalId, $relatedId, $this::AKENEO_ENTITY_NAME, $productSKU);
            }

            $results[] = $result;
        }

        return $results;
    }

    protected function getAttributeByCode($attrCode)
    {
        if (!array_key_exists($attrCode, $this->attributes)) {
            $this->attributes[$attrCode] = $this->connectorService->getAttributeByCode($attrCode);
        }

        return $this->attributes[$attrCode];
    }

    protected function getattributeSets()
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getAttributeSets');
        $method = 'GET';

        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);

            if (!empty($results['items'])) {
                return $results['items'];
            } else {
                return [];
            }
        } catch (Exception $e) {
            return [];
        }
    }
    
    protected function getFamilyVariant($productModel)
    {
        $sku = !empty($productModel['sku']) ? $productModel['sku'] : null;
        $attribute_set_id = !empty($productModel['attribute_set_id']) ?  $productModel['attribute_set_id'] : null;
        $family = null;
        $code = null;

        $this->attributesOptions = [];
        $attributes = [];

        if (!empty($productModel['extension_attributes']['configurable_product_options'])) {
            $options = $productModel['extension_attributes']['configurable_product_options'];
            foreach ($options as $option) {
                //  $optionCode  = $this->connectorService->findCodeByExternalId($option['attribute_id'], 'attribute');
                $optionCode = $this->getOptionCodeByAttributeId($option['attribute_id']);
                if (!empty($optionCode)) {
                    $this->attributesOptions[] = $this->connectorService->matchAttributeCodeInDb($optionCode); #? : $optionCode;
                }
            }
            $attributes = $this->attributesOptions;
            
            $url = $this->oauthClient->getApiUrlByEndpoint('addAttributeSet');
            $url = $url . '/' . $attribute_set_id;
            $method = 'GET';
            
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            
            $family = !empty($results['attribute_set_name']) ? $this->connectorService->getFamilyByCode($this->connectorService->convertToCode($results['attribute_set_name'])) : null;
            //add variant attributes in family as per mapping
            $attributeMappings = $this->connectorService->getAttributeMappings();
            $this->variantAttributes = $this->getVariantAttributes();
            $variantAttributes = [];
            foreach ($this->variantAttributes as $attribute) {
                if (in_array($attribute, array_keys($attributeMappings))) {
                    $variantAttributes[] = $attributeMappings[$attribute];
                }
            }
            $variantAttributes = array_unique(array_merge($variantAttributes, $attributes));
            if ($family) {
                $familyCode = $family->getCode();
                $restAttributes = array_diff($variantAttributes, $family->getAttributeCodes());
                //if attributes are not present in family
                if ($restAttributes) {
                    $this->connectorService->updateFamilyAttributes($family, $restAttributes, $this->scope);
                }
            } else {
                throw new \Exception($results['attribute_set_name']." family is not imported, import family first");
            }
            
            $code = preg_replace("/[^a-zA-Z]/", "", json_encode($attributes)).'_'.$familyCode;
            
            $label = preg_replace("/[^a-zA-Z]/", "", json_encode($attributes));
            
            $familyVariantMapping = $this->connectorService->getFamilyVariantByIdentifier($code);
            
            if (empty($familyVariantMapping)) {
                $content = [
                    'labels' =>  array(
                        $this->defaultLocale => $label,
                    ),
                    'variant_attribute_sets' => [
                        array(
                            'level' => 1,
                            'axes' => is_array($attributes) ? $attributes : array($attributes),
                            'attributes' => $variantAttributes ? $variantAttributes : [],
                            )
                    ],
                    'code' => $code,
                    'family' => $familyCode,
                ];
                
                $response = $this->connectorService->createFamilyVariant($content);
                $status = $response->getStatusCode();
                if ($status === Response::HTTP_BAD_REQUEST) {
                    $response = ($this->familyVariantObject->getAction($code));
                    throw new \Exception($response);
                }
            }
        }
       
        return $code;
    }

    protected function addProductLinks($productModel)
    {
        //Add configurable product links mapping  for product variant
        
        $externalId = !empty($productModel['id']) ? $productModel['id'] : null;
        $options = $productModel['extension_attributes']['configurable_product_options'] ?? [];

        foreach ($options as $option) {
            $optionCode = $this->getOptionCodeByAttributeId($option['attribute_id']);

            if (!empty($optionCode)) {
                $attributesOptions[] = $optionCode;
            }
        }

        $productLinks = $productModel['extension_attributes']['configurable_product_links'] ?? [];

        $extras = json_encode(
            [
                "configurable_product_links" => $productLinks ,
                "configurable_product_options" => $attributesOptions
            ]
        );
        
        $code =  $productModel['sku'];
        if ($code && $externalId) {
            //remove the previous mapping
            $mapping = $this->connectorService->removeAllMappingByEntity('product_links', $this->stepExecution->getJobExecution()->getId());
            //add the new mapping
            $mapping = $this->addMappingByCode($code, $externalId, null, 'product_links', $extras);
        }
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

            $error = ['error' => $lastResponse ];

            return $error;
        }
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

    protected function getVariantAttributes()
    {
        $values = [];

        $mapping = $this->connectorService->getSettings('magento2_child_attribute_mapping');
        if (!empty($mapping)) {
            $values = is_array($mapping) ? array_values($mapping) : [$mapping];
        }

        return $values;
    }

    protected function getOptionCodeByAttributeId($attributeId)
    {
        $attribute_set_id = !empty($attributeId) ? $attributeId : null;
        $url = $this->oauthClient->getApiUrlByEndpoint('getAttributes');
        $url = str_replace('{attributeCode}', $attributeId, $url);
        $method = 'GET';
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            if (!empty($results['attribute_code'])) {
                $code = $results['attribute_code'];
            } else {
                $code = '';
            }
        } catch (Exception $e) {
            $code = '';
        }

        return $code;
    }

    public function convertToCode($name)
    {
        return $this->connectorService->convertToCode($name);
    }
}
