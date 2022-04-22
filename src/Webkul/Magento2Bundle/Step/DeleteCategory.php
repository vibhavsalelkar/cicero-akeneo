<?php

namespace Webkul\Magento2Bundle\Step;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Entity\DataMapping;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();
/**
 * magento2 step implementation that read items, process them and write them using api, code in respective files
 *
 */
class DeleteCategory extends \AbstractStep
{
    protected $jsonHeaders = [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ];

    protected $oauthClient;
    protected $configurationService;
    protected $em;
    /**
     * @param string                   $name
     * @param EventDispatcherInterface $eventDispatcher
     * @param \JobRepositoryInterface    $jobRepository
     */
    public function __construct(
        $name,
        EventDispatcherInterface $eventDispatcher,
        \JobRepositoryInterface $jobRepository,
        Magento2Connector $configurationService,
        \Doctrine\ORM\EntityManager $em
    ) {
        parent::__construct($name, $eventDispatcher, $jobRepository);
        $this->configurationService = $configurationService;
        $this->em = $em;
    }
    /**
     * {@inheritdoc}
     */
    public function doExecute(\StepExecution $stepExecution)
    {
        try {
            if (!empty($stepExecution->getJobParameters()->all()['deleteCategory']) && $stepExecution->getJobParameters()->all()['deleteCategory']) {
                try {
                    $params = $this->configurationService->getCredentials();
                    $this->oauthClient = new OAuthClient(!empty($params['authToken']) ? $params['authToken'] : null, $params['hostName']);
                } catch (\Exception $e) {
                }
                $this->configurationService->setStepExecution($stepExecution);
                $categories = $this->configurationService->getDeletedCategoryFromAkeneo();
                
                if (\count($categories)) {
                    foreach ($categories as $value) {
                        $resp = $this->configurationService->getMappingByCode($value->getCode(), 'category');
                        if ($resp) {
                            $url = $this->oauthClient->getApiUrlByEndpoint('deleteCategory');
                            $url = str_replace('{id}', urlencode($resp->getExternalId()), $url);
                            $method = 'DELETE';
                            try {
                                $this->oauthClient->fetch($url, null, $method, $this->jsonHeaders);
                                $results = json_decode($this->oauthClient->getLastResponse(), true);
                            } catch (\Exception $e) {
                                $stepExecution->addWarning(
                                    'Error In deletion Of Category',
                                    [],
                                    new \DataInvalidItem(['debugLine' => __LINE__ ])
                                );
                            }
    
                            if ($resp && $resp instanceof DataMapping) {
                                $this->em->remove($resp);
                                $this->em->flush();
                            }
                            $stepExecution->incrementSummaryInfo('delete_category');
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $stepExecution->addWarning('Error In deletion Of Category', [], new \DataInvalidItem([]));
        }
    }
}
