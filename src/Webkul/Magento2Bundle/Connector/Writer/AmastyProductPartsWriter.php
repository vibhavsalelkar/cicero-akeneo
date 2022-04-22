<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

class AmastyProductPartsWriter extends BaseWriter implements \ItemWriterInterface
{
    use DataMappingTrait;

    const AKENEO_ENTITY_NAME = 'amasty_product_parts';
    const FIELD_NAME = 'product_part_field';
    const FINDER_ID = null;

    /* @var $dropDowns */
    protected $dropdowns;

    protected $mappingWarning = false;

    public function __construct(\Doctrine\ORM\EntityManager $em, Magento2Connector $connectorService)
    {
        $this->em = $em; /* used in DataMappingTrait */
        $this->connectorService = $connectorService;
    }

    /**
     * add product parts to magento2
     */
    public function write(array $items)
    {
        $parameters = $this->stepExecution->getJobParameters();
        $converterOptions = $this->getConverterOptions($parameters);
        if (empty($this->dropdowns)) {
            $this->dropdowns = $this->getAllDropdowns();
        }

        $converterOptions['locale'] = $this->defaultLocale;
        $settings = $this->connectorService->getAttributeMappings(Magento2Connector::SETTING_SECTION);
        $partsAttribute = !empty($settings[self::FIELD_NAME]) && !empty($settings[self::FIELD_NAME]) ? $settings[self::FIELD_NAME] : null;

        if ($partsAttribute) {
            foreach ($items as $item) {
                if (isset($item['values'][$partsAttribute])) {
                    $flag = false;
                    $identifier = $this->getItemIdentifier($item);

                    if ($identifier) {
                        /* localizable, scopable not supported */
                        $parts = $this->formatAttributeValue($item['values'][$partsAttribute], $converterOptions);
                        if (!empty($parts) && is_array($parts) && count(array_filter($parts))) {
                            $flag = $this->addOrUpdateParts($parts, $identifier, $partsAttribute) || $flag;
                        }
                    }
                    $this->logSummaryByFlag($flag);
                }
            }
        } else {
            if (!$this->mappingWarning) {
                $this->stepExecution->addWarning('Table Attributes for finder not mapped in settings', [], new \DataInvalidItem(['debugLine' => __LINE__]));
                $this->mappingWarning =  true;
            }
        }
    }

    protected function addOrUpdateParts($parts, $identifier, $partsAttribute)
    {
        $values = $previousData = [];
        $code = $mapping = $success = false;

        foreach ($parts as $part) {
            if (!$code) {
                $keys = array_keys(array_change_key_case($part, CASE_LOWER));
                sort($keys);
                $code = $identifier . '-' . $partsAttribute . '-' . implode(',', $keys);
            }

            if (!$mapping) {
                $mapping = $this->getMappingByCode($code, self::AKENEO_ENTITY_NAME);
                $previousData = $mapping && $mapping->getExtras() ? json_decode($mapping->getExtras(), true) : [];
            }
 
            $value = implode(
                ',',
                array_map('strtolower', array_values($part))
            );
            $values[] = $value;

            if (in_array($value, $previousData)) {
                $previousData = array_diff($previousData, [ $value ]);
            /* skip already exported */
            } else {
                $data = $this->formatDataForAttachment(
                    $identifier,
                    $partsAttribute,
                    $part
                );
                if ($data) {
                    $url = $this->oauthClient->getApiUrlByEndpoint('amastyProductParts');
                    $method = 'POST';
                    
                    try {
                        $this->oauthClient->fetch($url, $data, $method, $this->jsonHeaders);
                        $result = json_decode($this->oauthClient->getLastResponse(), true);
                    } catch (\Exception $e) {
                        $result = 0;
                    }
                    $success = ($result === true) ? true : false;
                }
            }
        }

        if (!empty($previousData) && count($previousData)) {
            foreach ($previousData as $data) {
                // $this->deletePreviousData($data);
            }
        }

        if ($success) {
            $this->addMappingByCode($code, 0, null, self::AKENEO_ENTITY_NAME, json_encode($values));
            return true;
        }
    }

    protected function getAllDropdowns()
    {
        $method = 'GET';
        $url = $this->oauthClient->getApiUrlByEndpoint('amastyPartsDropdown');
        $dropdowns = [];
        try {
            $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
            $result = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $result = [];
            $this->stepExecution->addWarning('dropdown fetch error: '. $e->getMessage(), [], new \DataInvalidItem(['debugLine' => __LINE__]));
        }

        foreach ($result as $dropdown) {
            if (!self::FINDER_ID || self::FINDER_ID == $dropdown['finder_id']) {
                $dropdowns[strtolower($dropdown['name'])] = $dropdown['dropdown_id'];
            }
        }

        return $dropdowns;
    }

    protected function formatDataForAttachment($identifier, $label, $values)
    {
        $data = [
            "sku" => $identifier,
            "dropdowns" => [
            ]
        ];

        $pos = 0;
        foreach ($values as $dropdownName => $value) {
            if (empty($this->dropdowns[strtolower($dropdownName)])) {
                $this->stepExecution->addWarning('dropdown not found for: ' . $dropdownName, [], new \DataInvalidItem([]));
                return;
            }
            if (isset($this->dropdowns[strtolower($dropdownName)])) {
                $data['dropdowns'][] = [
                    'dropdown_id' => $this->dropdowns[strtolower($dropdownName)],
                    'value'       => $value
                ];
            }
            $pos++;
        }

        return json_encode($data);
    }

    protected function createDropdown($dropdownName, $pos)
    {
        $method = 'POST';
        $url = $this->oauthClient->getApiUrlByEndpoint('addAmastyPartsDropdown');
        $dropdowns = [];
        $data = [
            "dropdown" => [
                // "dropdown_id" => 0,
                "finder_id" => (self::FINDER_ID ? : 0),
                "pos" => $pos,
                "name" => $dropdownName,
                "sort" => 0,
                "range" => 0,
                "display_type" => 0
            ]
        ];
        try {
            $this->oauthClient->fetch($url, $data, $method, $this->jsonHeaders);
            $result = json_decode($this->oauthClient->getLastResponse(), true);
        } catch (\Exception $e) {
            $result = [];
        }

        if (isset($result['dropdown_id'])) {
            $this->dropdowns[strtolower($result['name'])] = $result['dropdown_id'];
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
            $this->stepExecution->incrementSummaryInfo('process');
        }
    }
}
