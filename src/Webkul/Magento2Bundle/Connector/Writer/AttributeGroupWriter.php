<?php

namespace Webkul\Magento2Bundle\Connector\Writer;

use Webkul\Magento2Bundle\Component\Normalizer\PropertiesNormalizer;
use Webkul\Magento2Bundle\Connector\Writer\BaseWriter;
use Webkul\Magento2Bundle\Entity\Magento2Mapping;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Add attribute groups to magento2 Api
 *
 * @author    Webkul
 * @copyright 2010-2017 Webkul pvt. ltd.
 * @license   https://store.webkul.com/license.html
 */
class AttributeGroupWriter extends BaseWriter implements \ItemWriterInterface
{
    const AKENEO_ENTITY_NAME = 'group';

    public function __construct(\Doctrine\ORM\EntityManager $em, Magento2Connector $connectorService)
    {
        $this->em = $em;
        $this->connectorService = $connectorService;
    }

    /**
     * write attributeGroups to magento2 Api
     */
    public function write(array $items)
    {
        return;
        $parameters = $this->getParameters();
        $this->storeMapping = array_filter($this->getStoreMapping());
        $locales = array_keys($this->storeMapping);

        while (count($items)) {
            $errorMsg = false;
            $item = array_shift($items);
            foreach ($locales as $locale) {
                $name = !empty($item['labels'][$locale]) ? $item['labels'][$locale] : null;
                if (!$name) {
                    /* show error if translation of attribute group for locale is not present */
                    $errorMsg = 'Untranslated group with code: ' . $item['code']  .  ', for locale: ' . $locale ;
                    $this->stepExecution->addWarning($errorMsg, ['error' => true ], new \DataInvalidItem([]));
                    continue;
                }

                $mapping = $this->getMappingByCode($item['code']);
                $item['extension_attributes'] = [
                    'attribute_group_code' => $item['code'],
                    'sort_order'           =>  $item['sort_order'],
                ];
                $data = $this->createArrayFromDataAndMatcher($item, $this->matcher, SELF::AKENEO_ENTITY_NAME);

                /* check if attribute group already exist */
                if ($mapping) {
                    /* add attributeSet */
                    $data[self::AKENEO_ENTITY_NAME]['attribute_group_id'] = $mapping->getExternalId();

                    $resource = $this->addAttributeGroup($data);
                    if (!empty($resource['attribute_group_id'])) {
                        $this->addMappingByCode($item['code'], $resource['attribute_group_id'], $resource['attribute_set_id']);
                    }
                } else {
                    $resource = $this->addAttributeGroup($data);
                    if (!empty($resource['attribute_group_id'])) {
                        $this->addMappingByCode($item['code'], $resource['attribute_group_id'], $resource['attribute_set_id']);
                    } elseif (!empty($resource['error'])) {
                    }
                }
            }

            /* increment write count */
            if (!$errorMsg) {
                $this->stepExecution->incrementSummaryInfo('write');
            }
        }
    }

    protected function addAttributeGroup(array $resource)
    {
        $method = 'POST';
        $url = $this->oauthClient->getApiUrlByEndpoint('addAttributeGroup');

        try {
            $this->oauthClient->fetch($url, json_encode($resource), $method, $this->jsonHeaders);
            $results = json_decode($this->oauthClient->getLastResponse(), true);
            return $results;
        } catch (\Exception $e) {
            $error = ['error' => json_decode($this->oauthClient->getLastResponse(), true) ];
            return $error;
        }
    }
    
    protected $matcher = [
        'name'        => 'attribute_group_name',
        'extension_attributes' => 'extension_attributes',
    ];

    protected $filler = [
        'attribute_set_id' => '0',
    ];
}
