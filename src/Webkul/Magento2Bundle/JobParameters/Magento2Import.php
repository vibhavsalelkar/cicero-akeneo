<?php

namespace Webkul\Magento2Bundle\JobParameters;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints;
use Webkul\Magento2Bundle\Component\Validator\ValidJobCredentials;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Url;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

class Magento2Import implements
    \ConstraintCollectionProviderInterface,
    \DefaultValuesProviderInterface
{
    /** @var array */
    protected $supportedJobNames;

    /** @var \ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var \LocaleRepositoryInterface */
    protected $localeRepository;

    /**
     * @param \ChannelRepositoryInterface     $channelRepository
     * @param \LocaleRepositoryInterface      $localeRepository
     * @param array                          $supportedJobNames
     */
    public function __construct(
        \ChannelRepositoryInterface $channelRepository,
        \LocaleRepositoryInterface $localeRepository,
        array $supportedJobNames
    ) {
        $this->channelRepository = $channelRepository;
        $this->localeRepository = $localeRepository;
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
        /* temporary ( remove this ) */
        $parameters['filePath'] = '/tmp/category.csv';

        $channels = $this->channelRepository->getFullChannels();
        $defaultChannelCode = (0 !== count($channels)) ? $channels[0]->getCode() : null;

        $localesCodes = $this->localeRepository->getActivatedLocaleCodes();
        $defaultLocaleCode = (0 !== count($localesCodes)) ? $localesCodes[0] : null;

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
                'scope'   => $defaultChannelCode,
                'locales' => [$defaultLocaleCode],
                'locale' =>   $defaultLocaleCode,
            ],
        ];

        $parameters['with_media'] = true;
        $parameters['realTimeVersioning'] = false;
        $parameters['enabledComparison'] = false;
        $parameters['convertVariantToSimple'] = false;

        $parameters['hostName'] = '';
        

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getConstraintCollection()
    {
        $constraintFields = [];
        $constraintFields['user_to_notify'] = new Optional();
        $constraintFields['product_only'] = new Optional();
        $constraintFields['with_media'] = new Optional();
        
        $constraintFields['filters'] = [
            new Collection(
                [
                    'fields'           => [
                        'structure' => [
                            new \FilterStructureLocale(['groups' => ['Default', 'DataFilters']]),
                            new Collection(
                                [
                                    'fields'             => [
                                        'locales'    => new Optional(), //[ new NotBlank(), new Count(1)]
                                        'locale'     => new NotBlank(),
                                        'currency'   => new NotBlank(),
                                        'scope'      => new NotBlank(),
                                        'attributes' => new Type(
                                            [
                                                'type'  =>  'array',
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
        $constraintFields['with_media'] = new Optional();
        $constraintFields['realTimeVersioning'] = new Optional();
        $constraintFields['enabledComparison'] = new Optional();
        $constraintFields['product_only'] = new Optional();
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
}
