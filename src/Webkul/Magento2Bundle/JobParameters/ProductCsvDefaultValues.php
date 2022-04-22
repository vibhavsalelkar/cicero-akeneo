<?php

namespace Webkul\Magento2Bundle\JobParameters;

use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$versionCompatibily = new AkeneoVersionsCompatibility();
$versionCompatibily->checkVersionAndCreateClassAliases();

class ProductCsvDefaultValues implements \DefaultValuesProviderInterface
{
    /** @var \DefaultValuesProviderInterface */
    protected $simpleProvider;

    /** @var array */
    protected $supportedJobNames;

    /** @var \ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var \LocaleRepositoryInterface */
    protected $localeRepository;

    /**
     * @param \DefaultValuesProviderInterface $simpleProvider
     * @param \ChannelRepositoryInterface     $channelRepository
     * @param \LocaleRepositoryInterface      $localeRepository
     * @param array                          $supportedJobNames
     */
    public function __construct(
        \DefaultValuesProviderInterface $simpleProvider,
        \ChannelRepositoryInterface $channelRepository,
        \LocaleRepositoryInterface $localeRepository,
        array $supportedJobNames
    ) {
        $this->simpleProvider    = $simpleProvider;
        $this->channelRepository = $channelRepository;
        $this->localeRepository  = $localeRepository;
        $this->supportedJobNames = $supportedJobNames;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultValues()
    {
        $parameters = $this->simpleProvider->getDefaultValues();
        $parameters['decimalSeparator'] = \LocalizerInterface::DEFAULT_DECIMAL_SEPARATOR;
        $parameters['multiValueSeparator'] = ",";
        $parameters['dateFormat'] = \LocalizerInterface::DEFAULT_DATE_FORMAT;
        $parameters['with_media'] = true;
        $parameters['deleteProductIfSKUChanged'] = true;

        $channels = $this->channelRepository->getFullChannels();
        $defaultChannelCode = (0 !== count($channels)) ? $channels[0]->getCode() : null;

        $localesCodes = $this->localeRepository->getActivatedLocaleCodes();
        $defaultLocaleCodes = (0 !== count($localesCodes)) ? [$localesCodes[0]] : [];

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
                    'value'    => 0,
                ],
                [
                    'field'    => 'categories',
                    'operator' => \Operators::IN_CHILDREN_LIST,
                    'value'    => []
                ]
            ],
            'structure' => [
                'scope'   => $defaultChannelCode,
                'locales' => $defaultLocaleCodes,
            ],
        ];

        return $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(\JobInterface $job)
    {
        return in_array($job->getName(), $this->supportedJobNames);
    }
}
