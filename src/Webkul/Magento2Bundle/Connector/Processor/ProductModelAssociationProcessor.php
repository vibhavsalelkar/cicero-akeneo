<?php

namespace Webkul\Magento2Bundle\Connector\Processor;

use Webkul\Magento2Bundle\Traits\FileInfoTrait;
use Webkul\Magento2Bundle\Traits\StepExecutionTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Product processor to process and normalize entities to the standard format
 *
 */
class ProductModelAssociationProcessor extends \PimProductProcessor
{
    use FileInfoTrait;
    use StepExecutionTrait;
    
    /** @var NormalizerInterface */
    protected $normalizer;

    /** @var \ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var \ObjectDetacherInterface */
    protected $detacher;

    /** @var StepExecution */
    protected $stepExecution;

    /** @var \EntityWithFamilyValuesFillerInterface */
    protected $productValuesFiller;

    protected $channel;

    protected $connectorService;

  
    public function process($product, $recursiveCall = false)
    {
        $productAssociations = array();
        
        if (!$recursiveCall && $product instanceof \Product) {
            /* skip excess Product */
            return;
        }

        $parameters = $this->stepExecution->getJobParameters();

        if ($scope = $this->getChannelScope($this->stepExecution)) {
            $channel = $this->channelRepository->findOneByIdentifier($scope);
            $this->channel = $scope;
            ;
        }

        if (!$recursiveCall) {
            if (method_exists($this->productValuesFiller, 'fillMissingValues')) {
                $this->productValuesFiller->fillMissingValues($product);
            }
            $productStandard = $this->normalizer->normalize($product, 'standard', [
                'channels' => !empty($channel) ? [$channel->getCode()] : [],
                'locales'  => array_intersect(
                    !empty($channel) ? $channel->getLocaleCodes() : [],
                    $this->getFilterLocales($this->stepExecution)
                ),
            ]);
            if ($product instanceof \ProductModelInterface && method_exists($this->productValuesFiller, 'fromStandardFormat')) {
                $productStandard = $this->productValuesFiller->fromStandardFormat($productStandard);
            }
        } else {
            $productStandard = $product;
        }
        if (isset($productStandard['associations']) && isset($productStandard['code'])) {
            $productAssociations = array(
                'sku' => $productStandard['code'],
                'associations' =>$productStandard['associations']
            );
        }
        
        
        return $productAssociations;
    }
}
