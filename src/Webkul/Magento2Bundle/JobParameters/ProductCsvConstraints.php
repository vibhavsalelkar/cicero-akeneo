<?php

declare(strict_types=1);

namespace Webkul\Magento2Bundle\JobParameters;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints;
use Webkul\Magento2Bundle\Component\Validator\ValidCredentials;
use Webkul\Magento2Bundle\Component\Validator\ValidJobCredentials;
use Symfony\Component\Validator\Constraints\Choice;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

class ProductCsvConstraints implements \ConstraintCollectionProviderInterface
{
    /** @var \ConstraintCollectionProviderInterface */
    protected $simpleProvider;

    /** @var array */
    protected $supportedJobNames;

    /**
     * @param \ConstraintCollectionProviderInterface $simpleCsv
     * @param array                                 $supportedJobNames
     */
    public function __construct(\ConstraintCollectionProviderInterface $simpleCsv, array $supportedJobNames)
    {
        $this->simpleProvider = $simpleCsv;
        $this->supportedJobNames = $supportedJobNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraintCollection()
    {
        $baseConstraint = $this->simpleProvider->getConstraintCollection();
        $constraintFields = $baseConstraint->fields;
        $constraintFields['delimiter']  = [
            new NotBlank(['groups' => ['Default', 'FileConfiguration']]),
            new Choice(
                [
                    'choices' => [",", ";", "|", "||", "|||", ";;"],
                    'message' => 'The value must be one of , or ; or |',
                    'groups'  => ['Default', 'FileConfiguration'],
                ]
            ),
        ];
        $constraintFields['decimalSeparator'] = new NotBlank(['groups' => ['Default', 'FileConfiguration']]);
        $constraintFields['dateFormat'] = new NotBlank(['groups' => ['Default', 'FileConfiguration']]);
        $constraintFields['with_media'] = new Type(
            [
                'type'   => 'bool',
                'groups' => ['Default', 'FileConfiguration'],
            ]
        );
        $constraintFields['filters'] = [
            // $this->filterData(),
            new Collection(
                [
                    'fields'           => [
                        'structure' => [
                            new Optional(),
                            new Collection(
                                [
                                    'fields'             => [
                                        'locales'    => new NotBlank(['groups' => ['Default', 'DataFilters']]),
                                        'scope'      => new NotBlank(['groups' => ['Default', 'DataFilters']]),
                                        'attributes' => new Type(
                                            [
                                                'type'  => 'array',
                                                'groups' => ['Default', 'DataFilters'],
                                            ]
                                        ),
                                    ],
                                    'allowMissingFields' => true,
                                ]
                            ),
                        ],
                    ],
                    'allowExtraFields' => true,
                ]
            ),
        ];
        if ($filterData = $this->filterData()) {
            $constraintFields['filters'][] = $filterData;
        }

        $constraintFields['product_only'] = new Optional();
        $constraintFields['with_media'] = new Optional();
        $constraintFields['deleteProductIfSKUChanged'] = new Optional();
        $constraintFields['attributeExport'] = new Optional();
        if (isset($constraintFields['exportProfile'])) {
            $constraintFields['exportProfile'] = new ValidJobCredentials($constraintFields['exportProfile']);
        }

        return new Collection([
            'fields' => $constraintFields,
            'allowExtraFields' => true,
            'allowMissingFields' => true,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(\JobInterface $job)
    {
        return in_array($job->getName(), $this->supportedJobNames);
    }

    /**
     * filterdata
     * @param void
     * @return string|null $filterClassName
     */
    public function filterData()
    {
        $filterClassName = null;
        if (class_exists('Pim\Component\Connector\Validator\Constraints\FilterData')) {
            $filterClassName = new \Pim\Component\Connector\Validator\Constraints\FilterData(['groups' => ['Default', 'DataFilters']]);
        } elseif (class_exists('Pim\Component\Connector\Validator\Constraints\ProductFilterData')) {
            $filterClassName = new \Pim\Component\Connector\Validator\Constraints\ProductFilterData(['groups' => ['Default', 'DataFilters']]);
        } elseif (class_exists('Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\ProductFilterData')) {
            $filterClassName = new \Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\ProductFilterData(['groups' => ['Default', 'DataFilters']]);
        }

        return $filterClassName;
    }
}
