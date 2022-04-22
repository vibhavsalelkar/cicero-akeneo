<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

class AmastyProductAttachmentWriter extends BaseWriter implements \ItemWriterInterface
{
    use DataMappingTrait;

    const ATTACHMENT_ENTITY_NAME = 'amasty_attachment';
    const AKENEO_ENTITY_NAME = 'product';

    /* @var $storeIdsData */
    protected $storeIdsData;

    /* @var $customerGroups */
    protected $customerGroupsData;

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
        $settings = $this->connectorService->getAttributeMappings(Magento2Connector::SETTING_SECTION);
        if (!empty($settings['enable_amasty_attachment_export']) && !empty($settings['magento2_amasty_attachment_fields'])) {
            $attachmentAttributes =  $settings['magento2_amasty_attachment_fields'];
            $attachmentAttributes = json_decode($attachmentAttributes, true);

            foreach ($items as $item) {
                $flag = false;
                foreach ($attachmentAttributes as $attachmentAttribute) {
                    $identifier = $this->getItemIdentifier($item);
                    if ($identifier) {
                        if (isset($item['values'][$attachmentAttribute])) {
                            $value = $this->formatAttributeValue($item['values'][$attachmentAttribute], $converterOptions);
                            $label = $attachmentAttribute;
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

        /* skip already exported */
        if ($mapping && $mapping->getRelatedId() === $value) {
            return true;
        }

        /* delete extra attachment */
        if (empty($value) && $mapping && $mapping->getExternalId()) {
            $url = $this->oauthClient->getApiUrlByEndpoint('updateDeleteAmastyProductAttachment');
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
        if ($mapping && $mapping->getExternalId()) {
            $id = $mapping->getExternalId();
            $method = 'PUT';
            $url = $this->oauthClient->getApiUrlByEndpoint('updateDeleteAmastyProductAttachment');
            $url = str_replace('{id}', $mapping->getExternalId(), $url);
        } else {
            $method = 'POST';
            $url = $this->oauthClient->getApiUrlByEndpoint('amastyProductAttachment');
        }
        $data = $this->formatDataForAttachment(
            $identifier,
            $attribute,
            $value
        );

        if ($data) {
            try {
                $this->oauthClient->fetch($url, $data, $method, $this->jsonHeaders);
                $result = json_decode($this->oauthClient->getLastResponse(), true);
            } catch (\Exception $e) {
                $result = 0;
            }

            if (!empty($result['id'])) {
                $mapping = $this->addMappingByCode($code, $result['id'], $value, $this::ATTACHMENT_ENTITY_NAME);
                $success = true;
            }
        }

        return !empty($success) ? $success : false;
    }

    /**
    * returns
    * {
    *    "productAttachment": {
    *        "product_id": 1,
    *        "file_name": "123123213213",
    *        "file_url": "",
    *        "file_type": "file",
    *        "customer_groups": [
    *            {
    *                "store_id": 0,
    *                "customer_group_id": 0
    *            }
    *        ],
    *        "store_configs": [
    *            {
    *                "store_id": 0,
    *                "label": "rtgrt",
    *                "is_visible": 1,
    *                "position": 1,
    *                "show_for_ordered": 0,
    *                "customer_group_is_default": 1
    *            }
    *        ],
    *        "content": {
    *            "base64_encoded_data": "NTU1MjNnNGg1aGo1Nmo1Nmo1Nmo=",
    *            "type": "text/plain",
    *            "name": "mysql_slow6.log"
    *        }
    *    }
    * }
    */
    protected function formatDataForAttachment($identifier, $label, $value)
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
            $label = $this->connectorService->getAttributeLabelByCodeAndLocale($label, 'en_US') ? : $label;
            $data = [
                "productAttachment" => [
                    "product_id" => $productId,
                    "file_name" => $filename,
                    "file_url" => "",
                    "file_type" => "file",
                    "customer_groups" => $this->getAllCustomerGroups(),
                    "store_configs" => $this->getAllStoreIds($label),
                    "content" => [
                        "base64_encoded_data" => $base64Data,
                        "type" => "text/plain", // can modify
                        "name" => $filename
                    ]
                ]
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
    *
    * [
    *    {
    *        "store_id": 0,
    *        "label": "rtgrt",
    *        "is_visible": 1,
    *        "position": 1,
    *        "show_for_ordered": 0,
    *        "customer_group_is_default": 1
    *    }
    * ]
    */
    protected function getAllStoreIds($label)
    {
        if (empty($this->storeIdsData)) {
            $this->storeIdsData = [];
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
                        if (!in_array($storeView['id'], $ids)) {
                            $ids[] = $storeView['id'];
                            $this->storeIdsData[] = [
                                "store_id" => $storeView['id'],
                                "label" => $label ,
                                "is_visible" => 1,
                                "position" => 0,
                                "show_for_ordered" => 0,
                                "customer_group_is_default" => 1
                            ];
                        }
                    }
                }
            }
        }

        return $this->storeIdsData;
    }

    /**
    * memoize and return customerGroups by api
    * @return
    * [
    *     {
    *        "store_id": 0,
    *        "customer_group_id": 0
    *    }
    * ]
    */
    protected function getAllCustomerGroups()
    {
        if (empty($this->customerGroupsData)) {
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
                        if (!in_array($customerGroup['id'], $ids)) {
                            $ids[] = $customerGroup['id'];
                            $this->customerGroupsData[] = [
                                "store_id" => 0,
                                "customer_group_id" => 0
                            ];
                        }
                    }
                }
            }
        }

        return $this->customerGroupsData;
    }
}
