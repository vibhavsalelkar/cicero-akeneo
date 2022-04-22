<?php

namespace Webkul\Magento2BundleProductBundle\Datasource;

use Doctrine\Common\Persistence\ObjectManager;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Product datasource, executes elasticsearch query
 *
 */
class ProductDatasource extends \BaseDataSource
{
    /**
     * {@inheritdoc}
     */
    public function getResults()
    {
        $attributeIdsToDisplay = $this->getConfiguration('displayed_attribute_ids');
        $attributes = $this->getConfiguration('attributes_configuration');
        $attributeCodesToFilter = $this->getAttributeCodesToFilter($attributeIdsToDisplay, $attributes);
        
        $this->filterEntityWithValuesSubscriber->configure(
            \FilterEntityWithValuesSubscriberConfiguration::filterEntityValues($attributeCodesToFilter)
        );
        $this->pqb->addFilter('enabled', '=', TRUE);
        $entitiesWithValues = $this->pqb->execute();
        $this->initializeQueryBuilder('select');
        $this->pqb->addFilter('enabled', '=', FALSE);
        $entitiesWithValues2 = $this->pqb->execute();
        $context = [
            'locales'             => [$this->getConfiguration('locale_code')],
            'channels'            => [$this->getConfiguration('scope_code')],
            'data_locale'         => $this->getParameters()['dataLocale'],
            'association_type_id' => $this->getConfiguration('association_type_id', false),
            'current_group_id'    => $this->getConfiguration('current_group_id', false),
        ];
        $rows = ['data' => []];
        
        foreach ($entitiesWithValues as $entityWithValue) {
            $normalizedItem = $this->normalizeEntityWithValues($entityWithValue, $context);
            $rows['data'][] = new ResultRecord($normalizedItem);
        }
        foreach ($entitiesWithValues2 as $entityWithValue) {
            $normalizedItem = $this->normalizeEntityWithValues($entityWithValue, $context);
            $rows['data'][] = new ResultRecord($normalizedItem);
        }
        $rows['totalRecords'] = $entitiesWithValues->count() + $entitiesWithValues2->count();
        
        return $rows;
    }

    /**
     * Normalizes an entity with values with the complete set of fields required to show it.
     *
     * @param \EntityWithValuesInterface $item
     * @param array                     $context
     *
     * @return array
     */
    private function normalizeEntityWithValues(\EntityWithValuesInterface $item, array $context): array
    {
        
        $defaultNormalizedItem = [
            'id'               => $item->getId(),
            'dataLocale'       => $this->getParameters()['dataLocale'],
            'family'           => null,
            'values'           => [],
            'created'          => null,
            'updated'          => null,
            'label'            => null,
            'image'            => null,
            'groups'           => null,
            'enabled'          => null,
            'completeness'     => null,
            'variant_products' => null,
            'document_type'    => null,
        ];
        
        $normalizedItem = array_merge(
            $defaultNormalizedItem,
            $this->normalizer->normalize($item, 'datagrid', $context)
        );

        return $normalizedItem;
    }
    /**
     * @param array $attributeIdsToDisplay
     * @param array $attributes
     *
     * @return array array of attribute codes
     */
    private function getAttributeCodesToFilter(array $attributeIdsToDisplay, array $attributes): array
    {
        $attributeCodes = [];
        foreach ($attributes as $attribute) {
            if (in_array($attribute['id'], $attributeIdsToDisplay)) {
                $attributeCodes[] = $attribute['code'];
            }
        }

        return $attributeCodes;
    }
}
