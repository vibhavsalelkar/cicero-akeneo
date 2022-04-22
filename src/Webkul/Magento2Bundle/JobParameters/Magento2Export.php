<?php

namespace Webkul\Magento2Bundle\JobParameters;

use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Constraints\Collection;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

class Magento2Export implements
    \ConstraintCollectionProviderInterface,
    \DefaultValuesProviderInterface
{
    /** @var string[] */
    private $supportedJobNames;

    /**
     * @param \ChannelRepositoryInterface     $channelRepository
     * @param \LocaleRepositoryInterface      $localeRepository
     * @param string[] $supportedJobNames
     */
    public function __construct(
        \ChannelRepositoryInterface $channelRepository,
        \LocaleRepositoryInterface $localeRepository,
        array $supportedJobNames
    ) {
        $this->localeRepository  = $localeRepository;
        $this->channelRepository = $channelRepository;
        $this->supportedJobNames = $supportedJobNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValues()
    {
        $parameters = [
            'with_media' => true
            ];

        $channels = $this->channelRepository->getFullChannels();
        $channelsCode = [];
        foreach ($channels as $channel) {
            $channelsCode [] = $channel->getCode();
        }
        $localesCodes = $this->localeRepository->getActivatedLocaleCodes();
        
        $parameters['filters'] = [
            'data'      => [
                [
                    'field'    => 'enabled',
                    'operator' => \Operators::EQUALS,
                    'value'    => true,
                ],
                [
                    'field'    => 'completeness',
                    'operator' => \Operators::GREATER_OR_EQUAL_THAN,
                    'value'    => 100,
                ],
                [
                    'field'    => 'categories',
                    'operator' => \Operators::IN_CHILDREN_LIST,
                    'value'    => []
                ]
            ],
            'structure' => [
                'scope'   => $channelsCode,
                'locales' => $localesCodes,
            ],
        ];

        $parameters['hostName'] = '';
        $parameters['deleteProductIfSKUChanged'] = true;
        $parameters['exportSelectedCategory'] = true;
        

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraintCollection()
    {
        $constraintFields['user_to_notify'] = new Optional();
        /* more strict filter, structure contraint */
        $constraintFields['filters'] = [
            new Collection(
                [
                    'fields'           => [
                        'structure' => [
                            new \FilterStructureLocale(),
                            new Collection(
                                [
                                    'fields'             => [
                                        'locales'    => new NotBlank(),
                                        'scope'      => new NotBlank(),
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
        $constraintFields['exportProfile'] = [ new NotBlank(), new Constraints\NotEqualTo(0) ];

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
        }

        return $filterClassName;
    }
}
