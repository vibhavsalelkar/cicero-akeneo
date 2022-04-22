<?php

namespace Webkul\Magento2Bundle\Connector\Reader;

use Doctrine\Common\Persistence\ObjectRepository;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();
/**
 * attribute option reader
 */
class MetricOptionReader extends \AbstractReader implements
    \ItemReaderInterface,
    \InitializableInterface,
    \StepExecutionAwareInterface
{
    /** @var ObjectRepository */
    protected $repository;

    protected $connectorService;

    protected $credentials;

    protected $defaultLocale;

    /**
     * @param ObjectRepository $repository
     */
    public function __construct(
        ObjectRepository $repository,
        Magento2Connector $connectorService
    ) {
        $this->repository = $repository;
        $this->connectorService = $connectorService;
    }

    /**
     * {@inheritdoc}
    */
    protected function getResults()
    {
        $mappedStandard= array_values($this->connectorService->getAttributeMappings());
        $mappedCustom = [];
        if (isset($this->stepExecution->getJobParameters()->get('filters')['attributeExport'])) {
            $mappedCustom = $this->stepExecution->getJobParameters()->get('filters')['attributeExport'];
        }
        $mappedAll = array_merge($mappedCustom, $mappedStandard);

        if (!$this->credentials) {
            $this->credentials = $this->connectorService->getCredentials();
        }

        if (!empty($this->credentials['storeMapping']['allStoreView']['locale']) && in_array($this->credentials['storeMapping']['allStoreView']['locale'], $this->connectorService->getActiveLocales())) {
            $this->defaultLocale = $this->credentials['storeMapping']['allStoreView']['locale'];
        } else {
            throw new \Exception("Default Locale Not Found, Set the Default Locale in the Store Mapping");
        }
        
        $selectAttrs = $this->connectorService->getSelectTypeAttributes();
        $axeAttributes = $this->connectorService->attributesAxesOptions();

        $selectedAttrs = array_intersect_key($axeAttributes, array_flip($mappedAll));
     
        $allOptions = $this->formateOption($selectedAttrs);
        return new \ArrayIterator($allOptions);
    }

    public function formateOption($selectedAttrs)
    {
        $optionData = [];
        if (isset($selectedAttrs)) {
            $i=0;
            foreach ($selectedAttrs as $key => $values) {
                foreach ($values as $value) {
                    $optionData[] = [
                        "code" => $value,
                        "attribute" => $key,
                        "sort_order" => $i,
                        "labels" => [
                            $this->defaultLocale => $value
                        ]
                    ];
                    $i++;
                }
            }
        }

        return $optionData;
    }
}
