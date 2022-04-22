<?php

namespace Webkul\Magento2Bundle\Step;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * magento2 step implementation that read items, process them and write them using api, code in respective files
 *
 */
class BaseStep extends \ItemStep
{
    const STEP_NAME_PRODUCT_EXPORT = 'product_export';
    const STEP_NAME_PRODUCT_MODEL_EXPORT = 'product_model_export';
    const STEP_NAME_PRODUCT_ASSOCIATION_EXPORT = 'product_association_export';
    const STEP_NAME_PRODUCT_MODEL_ASSOCIATION_EXPORT = 'product_model_association_export';
    const STEP_NAME_CREDENTIALS_CHECK = 'credential_check';
  
    protected $jobStopper;

    /**
     * @param string                   $name
     * @param EventDispatcherInterface $eventDispatcher
     * @param \JobRepositoryInterface    $jobRepository
     * @param \ItemReaderInterface      $reader
     * @param \ItemProcessorInterface   $processor
     * @param \ItemWriterInterface      $writer
     * @param integer                  $batchSize
     */
    public function __construct(
        $name,
        EventDispatcherInterface $eventDispatcher,
        \JobRepositoryInterface $jobRepository,
        \Doctrine\ORM\EntityManager $em,
        ?\ItemReaderInterface $reader,
        \ItemProcessorInterface $processor,
        \ItemWriterInterface $writer,
        $batchSize = 100
    ) {
        parent::__construct($name, $eventDispatcher, $jobRepository, $reader, $processor, $writer, $batchSize);
        $this->name = $name;
        $this->eventDispatcher = $eventDispatcher;
        $this->jobRepository = $jobRepository;
        $this->em = $em;
        $this->reader = $reader;
        $this->processor = $processor;
        $this->writer = $writer;
       
    }

    // /**
    //  * Get reader
    //  *
    //  * @return \ItemReaderInterface
    //  */
    // public function getReader()
    // {
    //     return $this->reader;
    // }

    // /**
    //  * Get processor
    //  *
    //  * @return \ItemProcessorInterface
    //  */
    // public function getProcessor()
    // {
    //     return $this->processor;
    // }

    // /**
    //  * Get writer
    //  *
    //  * @return \ItemWriterInterface
    //  */
    // public function getWriter()
    // {
    //     return $this->writer;
    // }

    /**
     * {@inheritdoc}
     */
    public function doExecute(\StepExecution $stepExecution)
    {
        $itemsToWrite = [];
        $batchCount = 0;

        if ($stepExecution->getStepName() === self::STEP_NAME_CREDENTIALS_CHECK) {
            return;
        }

        /* types category_export, attribute_export, attribute_option, families_export, product_export */
        if ($stepExecution->getJobParameters()->has('product_only') && $stepExecution->getJobParameters()->get('product_only')) {
            if ($stepExecution->getStepName() != self::STEP_NAME_PRODUCT_EXPORT) {
                return;
            }
        }
        $this->updateJobFilterParameters($stepExecution);
        
        $this->initializeStepElements($stepExecution);
        
        while (true) {
            try {
                $readItem = $this->reader->read();
                if (null === $readItem) {
                    break;
                }
            } catch (InvalidItemException $e) {
                $this->handleStepExecutionWarning($this->stepExecution, $this->reader, $e);
                //$this->updateProcessedItems();

                continue;
            }

            $batchCount++;
            $processedItem = $this->process($readItem);
            if (null !== $processedItem) {
                $itemsToWrite[] = $processedItem;
            }

            if ($batchCount >= $this->batchSize) {
                if (!empty($itemsToWrite)) {
                    $this->write($itemsToWrite);
                    $itemsToWrite = [];
                }

                //$this->updateProcessedItems($batchCount);
                $this->dispatchStepExecutionEvent(\EventInterface::ITEM_STEP_AFTER_BATCH, $stepExecution);
                $batchCount = 0;
                if (null !== $this->jobStopper && $this->jobStopper->isStopping($stepExecution)) {
                    $this->jobStopper->stop($stepExecution);

                    break;
                }
            }
        }


        if (!empty($itemsToWrite)) {
            $this->write($itemsToWrite);
        }

        if ($batchCount > 0) {
            //$this->updateProcessedItems($batchCount);
            $this->dispatchStepExecutionEvent(\EventInterface::ITEM_STEP_AFTER_BATCH, $stepExecution);
        }

        if (null !== $this->jobStopper && $this->jobStopper->isStopping($stepExecution)) {
            $this->jobStopper->stop($stepExecution);
        }

        $this->flushStepElements();
    }

    /**
     * @param \StepExecution $stepExecution
     */
    protected function initializeStepElements(\StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
        foreach ($this->getStepElements() as $element) {
            if ($element instanceof \StepExecutionAwareInterface) {
                $element->setStepExecution($stepExecution);
            }
            if ($element instanceof \InitializableInterface) {
                $element->initialize();
            }
        }
    }

    /**
     * Flushes step elements
     */
    public function flushStepElements()
    {
        foreach ($this->getStepElements() as $element) {
            if ($element instanceof \FlushableInterface) {
                $element->flush();
            }
        }
    }


    private function updateProcessedItems(int $processedItemsCount = 1): void
    {
        $this->stepExecution->incrementProcessedItems($processedItemsCount);
        $this->jobRepository->updateStepExecution($this->stepExecution);
    }
    
    /**
     * @param mixed $readItem
     *
     * @return mixed processed item
     */
    protected function process($readItem)
    {
        try {
            return $this->processor->process($readItem);
        } catch (\InvalidItemException $e) {
            $this->handleStepExecutionWarning($this->stepExecution, $this->processor, $e);

            return null;
        }
    }

    /**
     * @param array $processedItems
     */
    protected function write($processedItems)
    {
        try {
            $this->writer->write($processedItems);
        } catch (\InvalidItemException $e) {
            $this->handleStepExecutionWarning($this->stepExecution, $this->writer, $e);
        }
    }


    /**
     * Get the configurable step elements
     *
     * @return array
     */
    protected function getStepElements()
    {
        return [
            'reader'    => $this->reader,
            'processor' => $this->processor,
            'writer'    => $this->writer
        ];
    }

    protected $jobFilters;

    protected function updateJobFilterParameters(\StepExecution &$stepExecution)
    {
        $filters = $this->getJobStepFilters($stepExecution);
        if (in_array($stepExecution->getStepName(), [self::STEP_NAME_PRODUCT_EXPORT, self::STEP_NAME_PRODUCT_ASSOCIATION_EXPORT])) {
            $removeFilters = ['completeness_model', 'identifier'];
            $this->removeFilters($filters, $removeFilters);
        }
        
        if (in_array($stepExecution->getStepName(), [self::STEP_NAME_PRODUCT_MODEL_EXPORT, self::STEP_NAME_PRODUCT_MODEL_ASSOCIATION_EXPORT])) {
            $removeFilters = ['enabled', 'completeness', 'sku'];
            $this->removeFilters($filters, $removeFilters);
            $this->renameFiltersFieldKey($filters, 'completeness_model', 'completeness');
        }

        $stepExecution->getJobParameters()->set('filters', $filters);
    }

    protected function getJobStepFilters($stepExecution)
    {
        $rawParams = $stepExecution->getJobExecution()->getJobInstance()->getRawParameters();
        if (empty($rawParams['customjobFilters'])) {
            $rawParams['customjobFilters'] = $stepExecution->getJobParameters()->get('filters');
            $stepExecution->getJobExecution()->getJobInstance()->setRawParameters($rawParams);
        }

        return $rawParams['customjobFilters'];
    }

    protected function removeFilters(array &$filters, array $fields)
    {
        if (isset($filters['data'])) {
            foreach ($fields as $field) {
                $index = array_search($field, array_column($filters['data'], 'field'));
                if (false !== $index) {
                    array_splice($filters['data'], $index, 1);
                }
            }
        }
    }

    protected function renameFiltersFieldKey(array &$filters, $searchKey, $updateKey)
    {
        if (isset($filters['data'])) {
            $index = array_search($searchKey, array_column($filters['data'], 'field'));
            if (false !== $index) {
                $filters['data'][$index]['field'] = $updateKey;
            }
        }
    }
}
