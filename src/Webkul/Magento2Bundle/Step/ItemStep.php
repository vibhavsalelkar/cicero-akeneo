<?php

namespace Webkul\Magento2Bundle\Step;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();
/**
 * magento2 step implementation that read items, process them and write them using api, code in respective files
 *
 */
class ItemStep extends \AbstractStep
{
    protected $configurationService;
    /**
     * @param string                   $name
     * @param EventDispatcherInterface $eventDispatcher
     * @param \JobRepositoryInterface    $jobRepository
     */
    public function __construct(
        $name,
        EventDispatcherInterface $eventDispatcher,
        \JobRepositoryInterface $jobRepository,
        Magento2Connector $configurationService
    ) {
        parent::__construct($name, $eventDispatcher, $jobRepository);
        $this->configurationService = $configurationService;
    }
    /**
     * {@inheritdoc}
     */
    public function doExecute(\StepExecution $stepExecution)
    {
        try {
            $this->configurationService->setStepExecution($stepExecution);
            $credentials = $this->configurationService->getCredentials();
            
            $host = !empty($credentials['hostName']) ? $credentials['hostName'] : 'Not Found';
            $stepExecution->addSummaryInfo('host', $host);
            $storeViews = json_decode($this->configurationService->validateCredentials(), true);
        
            $storeViewsCodes = [];
          
            foreach ($storeViews as $storeView) {
                if (isset($storeView['website_id']) && isset($storeView['code']) && $storeView['website_id']) {
                    $storeViewsCodes[] = $storeView['code'];
                }
            }

            $storeMapping = array_keys($this->configurationService->getStoreMapping());
            
            if (!empty(array_diff($storeMapping, $storeViewsCodes)) && count(array_diff($storeMapping, $storeViewsCodes)) != 1) {
                $stepExecution->addWarning('Store Mapping is not saved', [], new \DataInvalidItem([
                    'previews store mapping' => $storeMapping,
                    'storeViews' => $storeViewsCodes,
                ]));
                $stepExecution->setTerminateOnly();
            }
        } catch (Exception $e) {
            $stepExecution->addWarning('Invalid Credential or Expired', [], new \DataInvalidItem([]));
            $stepExecution->setTerminateOnly();
        }
    }
}
