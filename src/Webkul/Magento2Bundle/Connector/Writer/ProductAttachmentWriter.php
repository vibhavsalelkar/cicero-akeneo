<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

class ProductAttachmentWriter extends BaseWriter implements \ItemWriterInterface
{
    use DataMappingTrait;

    const ATTACHMENT_ENTITY_NAME = 'attachment';

    /* @var $storeIds */
    protected $storeIds;

    /* @var $customerGroups */
    protected $customerGroups;

    protected $mappingWarning = false;

    public function __construct(\Doctrine\ORM\EntityManager $em, Magento2Connector $connectorService)
    {
        $this->em = $em; /* used in DataMappingTrait */
        $this->connectorService = $connectorService;
    }

    /**
     * add product attachments to magento2
     */
    public function write(array $items)
    {
        $parameters = $this->stepExecution->getJobParameters();
        $converterOptions = $this->getConverterOptions($parameters);

        $converterOptions['locale'] = $this->defaultLocale;
        $attachmentAttributes = $this->connectorService->getAttributeMappings(Magento2Connector::SETTING_SECTION)['magento2_attachment_fields'] ?? '[]';

        if ($attachmentAttributes !== '[]') {
            $attachmentAttributes = json_decode($attachmentAttributes, true);

            foreach ($items as $item) {
                $flag = false;
                foreach ($attachmentAttributes as $attachmentAttribute) {
                    $identifier = $this->getItemIdentifier($item);
                    if ($identifier) {
                        if (isset($item['values'][$attachmentAttribute])) {
                            $value = $this->formatAttributeValue($item['values'][$attachmentAttribute], $converterOptions);
                            $label = $converterOptions['locale'] ? $this->connectorService->getAttributeLabelByCodeAndLocale($attachmentAttribute, $converterOptions['locale']) : $attachmentAttribute;
                            
                            $flag = $this->addOrUpdateAttachment($identifier, $label, $value) || $flag;
                        }
                    }
                }

                $this->logSummaryByFlag($flag);
            }
        } else {
            if (!$this->mappingWarning) {
                $this->stepExecution->addWarning('Attributes for attachment not mapped in settings', [], new \DataInvalidItem(['debugLine' => __LINE__]));
                $this->mappingWarning =  true;
            }
        }
    }

    protected function addOrUpdateAttachment($identifier, $attribute, $value)
    {
        $code = $identifier . '-' . $attribute;
        $mapping = $this->getMappingByCode($code, self::ATTACHMENT_ENTITY_NAME);
        
        /* get the attachement if does not exist at magento add again otherwise skip as already exported */
        if ($mapping && $mapping->getRelatedId() === $value) {
            $url = $this->oauthClient->getApiUrlByEndpoint('getProductAttachment');
            $url = str_replace('{id}', $mapping->getExternalId(), $url);
            try {
                $this->oauthClient->fetch($url, null, 'GET', $this->jsonHeaders);
                $result = json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
                $result = [];
            }
            if (empty($result)) {
                $mapping = $this->deleteMapping($mapping);
            } else {
                return true;
            }
        }
        
        /* delete extra attachment */
        if (empty($value) && $mapping && $mapping->getExternalId()) {
            $url = $this->oauthClient->getApiUrlByEndpoint('deleteProductAttachment');
            $url = str_replace('{id}', $mapping->getExternalId(), $url);
            try {
                $this->oauthClient->fetch($url, null, 'DELETE', $this->jsonHeaders);
                $result = json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
                $result = [];
            }
            $this->deleteMapping($mapping);

            return true;
        }

        /* add/update attachment */
        $method = 'POST';
        $data = $this->formatDataForAttachment(
            $identifier,
            $attribute,
            $value,
            $mapping ? $mapping->getExternalId() : 0
        );

        if ($data) {
            $url = $this->oauthClient->getApiUrlByEndpoint('productAttachment');
            try {
                $this->oauthClient->fetch($url, $data, $method, $this->jsonHeaders);
                $result = json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
                $result = 0;
            }
            
            if (is_numeric($result) && $result) {
                $mapping = $this->addMappingByCode($code, $result, $value, $this::ATTACHMENT_ENTITY_NAME);
                $success = true;
            }
        }

        return $success ?? false;
    }

    /**
    * returns
    * {
    * 	"productattachTable": {
    * 	    "productAttachId": 10,
    *         "name": "name-attachment",
    *         "description": "desc",
    * 		  "file": "abc.png",
    *         "url": "",
    *         "products": "2135",
    *         "active": "1",
    *         "store": "0",
    *         "customerGroup": "0"
    * 	},
    * 	"filename": "abc.png",
    * 	"fileContent": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mOMYPh/BAAELAIdP91vLwAAAABJRU5ErkJggg=="
    * }
    */
    protected function formatDataForAttachment($identifier, $label, $value, $externalId = 0)
    {
        $filename = explode('/', urldecode($value));
        $filename = end($filename);
        $filename = substr($filename, strpos($filename, '_')+1);
        try {
            $base64Data = base64_encode($this->connectorService->getImageContentByPath($value));
        } catch (\Exception $e) {
        }

        $productId = $this->getProductIdByIdentifier($identifier);

        if (!empty($base64Data) && $productId) {
            $data = [
                "productattachTable" => [
                     "productAttachId" => $externalId,
                    "name" => $label,
                    "description" => $label,
                    "file" => $filename,
                    "url" => "",
                    "products" => $productId,
                    "active" => "1",
                    "store" => $this->getAllStoreIds(),
                    "customerGroup" => $this->getAllCustomerGroups()
                ],
                "filename" => $filename,
                "fileContent" => $base64Data
            ];
            
            return json_encode($data);
        }
    }

    protected function getProductIdByIdentifier($identifier)
    {
        $url = $this->oauthClient->getApiUrlByEndpoint('getProduct');
        $url = str_replace('{sku}', $identifier, $url) . '?fields=id';

        try {
            $this->oauthClient->fetch($url, null, 'GET', $this->jsonHeaders);
            $result = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $result = [];
        }

        return $result['id'] ?? null;
    }

    /**
    * @param JobParameters $parameters
    *
    * @return array
    */
    protected function getConverterOptions(\JobParameters $parameters)
    {
        $options = [];

        if ($parameters->has('filters') && isset($parameters->get('filters')['structure']['scope'])) {
            $options['scope'] = $parameters->get('filters')['structure']['scope'];
        }

        if ($parameters->has('with_media')) {
            $options['with_media'] = $parameters->get('with_media');
        }

        if ($parameters->has('decimalSeparator')) {
            $options['decimal_separator'] = $parameters->get('decimalSeparator');
        }

        if ($parameters->has('dateFormat')) {
            $options['date_format'] = $parameters->get('dateFormat');
        }

        if ($parameters->has('ui_locale')) {
            $options['locale'] = $parameters->get('ui_locale');
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemIdentifier(array $product)
    {
        return isset($product['code']) ? $product['code'] : $product['identifier'];
    }

    /**
    * convert atribute value data of localized, scopable or normal to scalar value
    */
    protected function formatAttributeValue($data, $options)
    {
        $result = '';
        foreach ($data as $value) {
            if ($value['locale'] && $value['locale'] !== $options['locale']
                ||
                $value['scope'] && $value['scope'] !== $options['scope']) {
                continue;
            }
            $result = $value['data'];
            break;
        }

        return $result;
    }

    protected function logSummaryByFlag($flag)
    {
        if ($flag) {
            $this->stepExecution->incrementSummaryInfo('write');
        } else {
            $this->stepExecution->incrementSummaryInfo('attachment_not_found');
        }
    }

    /**
    * memoize and return storeIds by api
    */
    protected function getAllStoreIds()
    {
        if (empty($this->storeIds)) {
            $url = $this->oauthClient->getApiUrlByEndpoint('storeViews');
            try {
                $this->oauthClient->fetch($url, null, 'GET', $this->jsonHeaders);
                $result = json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
                $result = [];
            }

            if (!empty($result)) {
                $ids = [];
                foreach ($result as $storeView) {
                    if (isset($storeView['id'])) {
                        $ids[] = $storeView['id'];
                        $ids = array_unique($ids);
                    }
                }
                $this->storeIds = implode(',', $ids);
            }
        }

        return $this->storeIds;
    }

    /**
    * memoize and return customerGroups by api
    */
    protected function getAllCustomerGroups()
    {
        if (empty($this->customerGroups)) {
            $url = $this->oauthClient->getApiUrlByEndpoint('customerGroups');
            try {
                $this->oauthClient->fetch($url, null, 'GET', $this->jsonHeaders);
                $result = json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
                $result = [];
            }

            if (!empty($result['items'])) {
                $ids = [];
                foreach ($result['items'] as $customerGroup) {
                    if (isset($customerGroup['id'])) {
                        $ids[] = $customerGroup['id'];
                        $ids = array_unique($ids);
                    }
                }
                $this->customerGroups = implode(',', $ids);
            }
        }

        return $this->customerGroups;
    }
}
