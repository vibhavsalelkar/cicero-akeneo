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
class ProductAssociationProcessor extends \PimProductProcessor
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

    /**
     * @param NormalizerInterface                   $normalizer
     * @param \ObjectDetacherInterface               $detacher
     * @param \ChannelRepositoryInterface            $channelRepository
     * @param \EntityWithFamilyValuesFillerInterface $productValuesFiller
     */
    public function __construct(
        NormalizerInterface $normalizer,
        \ObjectDetacherInterface $detacher,
        \ChannelRepositoryInterface $channelRepository,
        \EntityWithFamilyValuesFillerInterface $productValuesFiller
    ) {
        $this->normalizer = $normalizer;
        $this->detacher = $detacher;
        $this->channelRepository = $channelRepository;
        $this->productValuesFiller = $productValuesFiller;
    }

    /**
     * {@inheritdoc}
     */
    public function process($product, $recursiveCall = false)
    {
        $productAssociations = array();
        
        if (!$recursiveCall && $product instanceof \ProductModel) {
            /* skip excess ProductModel */
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
        if (!empty($productStandard['parent'])) {
            $this->stepExecution->incrementSummaryInfo('read', -1);
            return;
        }
        if (isset($productStandard['associations']) && isset($productStandard['metadata']['identifier'])) {
            $productAssociations = array(
                'sku' => $productStandard['metadata']['identifier'],
                'associations' =>$productStandard['associations']
            );
        }
                
        return $productAssociations;
    }
}
