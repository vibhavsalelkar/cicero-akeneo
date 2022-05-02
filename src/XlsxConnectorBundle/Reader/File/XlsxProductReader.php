<?php

namespace Acme\Bundle\XlsxConnectorBundle\Reader\File;

use Akeneo\Tool\Component\Batch\Item\FileInvalidItem;
use Akeneo\Tool\Component\Batch\Item\FlushableInterface;
use Akeneo\Tool\Component\Batch\Item\InvalidItemException;
use Akeneo\Tool\Component\Batch\Item\ItemReaderInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Tool\Component\Connector\Reader\File\FileIteratorFactory;
use Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface;
use Akeneo\Tool\Component\Connector\Exception\DataArrayConversionException;
use Akeneo\Tool\Component\Connector\Exception\InvalidItemFromViolationsException;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Akeneo\Tool\Component\Connector\Reader\File\MediaPathTransformer;

class XlsxProductReader implements
    ItemReaderInterface,
    StepExecutionAwareInterface,
    FlushableInterface
{
    protected $fileIteratorFactory;
    protected $mediaPathTransformer;

    protected $fileIterator;

    /** @var array */
    protected $xlsx;

    /** @var StepExecution */
    protected $stepExecution;

    /** @var ArrayConverterInterface */
    protected $converter;

  /** @var AttributeRepositoryInterface */
  protected $attributeRepository;

  /** @var array */
  protected $options;

    /**
     * @param ArrayConverterInterface $converter
     */
    public function __construct(
        ArrayConverterInterface $converter, 
        FileIteratorFactory $fileIteratorFactory, 
        AttributeRepositoryInterface $attributeRepository,
        MediaPathTransformer $mediaPathTransformer,
        array $options = [])
    {
        $this->fileIteratorFactory = $fileIteratorFactory;
        $this->converter = $converter;
        $this->attributeRepository = $attributeRepository;
        $this->mediaPathTransformer = $mediaPathTransformer;
        $this->options = $options;
    }

    public function totalItems(): int
    {
        $jobParameters = $this->stepExecution->getJobParameters();
        $filePath = $jobParameters->get('filePath');
        $iterator = $this->fileIteratorFactory->create($filePath, $this->options);

        return max(iterator_count($iterator) - 1, 0);
    }

    public function read()
    {
        $clientBuilder = new \Akeneo\Pim\ApiClient\AkeneoPimClientBuilder('http://akeneorepo.local.com/');
        $client = $clientBuilder->buildAuthenticatedByPassword('7_4x6el698r4aooggs8wkcsk8k4co8cksgkco8o8c8c8o0gcg00s', '26fdmckc4n284ssw444ggwkkg0kg448ok0ksgws08swo88oo88', 'admin_9129', '2716469b2');
      
        $jobParameters = $this->stepExecution->getJobParameters();
        $filePath = $jobParameters->get('filePath');
        $filename = basename($filePath, '.xlsx');
        $filenamecode = strtolower($filename);
        if(str_contains($filenamecode, " ")){
            $filenamecode = str_replace(' - ', '_', $filenamecode);
            $filenamecode = str_replace('-', '_', $filenamecode);
            $filenamecode = str_replace(' ', '_', $filenamecode);
        }

        if (null === $this->fileIterator) {
            $this->fileIterator = $this->fileIteratorFactory->create($filePath, $this->options);
            $this->fileIterator->rewind();
        }

        $this->fileIterator->next();

        if ($this->fileIterator->valid() && null !== $this->stepExecution) {
            $this->stepExecution->incrementSummaryInfo('item_position');
        }

        $data = $this->fileIterator->current();

        if (null === $data) {
            return null;
        }

        $headers = $this->fileIterator->getHeaders();

        $countHeaders = count($headers);
        $countData = count($data);

        $this->checkColumnNumber($countHeaders, $countData, $data, $filePath);

        if ($countHeaders > $countData) {
            $missingValuesCount = $countHeaders - $countData;
            $missingValues = array_fill(0, $missingValuesCount, '');
            $data = array_merge($data, $missingValues);
        }

        $item = array_combine($this->fileIterator->getHeaders(), $data);
        
        if (isset($item['3M ID'])) {
            $item['sku'] = $item['3M ID'];
            unset($item['3M ID']);
        }

        if (isset($item['short_description'])){
            $item['Short_description-en_US-ecommerce'] = $item['short_description'];
            unset($item['short_description']);
        }

        foreach(array_keys($item) as $attribute){
            $oldAttribute = $attribute;
            if(str_contains($attribute, " ")){
                $attribute = str_replace(' - ', '_', $attribute);
                $attribute = str_replace('-', '_', $attribute);
                $attribute = str_replace(' ', '_', $attribute);
            }
            if(strcmp($attribute, $oldAttribute)!==0){
                $item[strtolower($attribute)] = $item[$oldAttribute];
                unset($item[$oldAttribute]);
            }
        }
        foreach(array_keys($item) as $attribute){
            if(!$item[$attribute])
                unset($item[$attribute]);
        }
        $product_info_value = '';
        $supported_attr = [];
        foreach(array_keys($item) as $attribute) {
            $attributes = $this->attributeRepository->findBy(['code' => $attribute]);

            if(!empty($attributes)) {
                array_push($supported_attr, $attribute);
            }

            if($attribute !== 'sku' && $attribute !== 'Short_description-en_US-ecommerce') {
                $product_info_value = $product_info_value.$attribute.':'.$item[$attribute].';';
                unset($item[$attribute]);
            }   
        }
        
        $client->getCategoryApi()->upsert($filenamecode, [
            'parent' => 'master',
            'labels' => [
                'en_US' => $filename,
                'fr_FR' => $filename,
                'de_DE' => $filename,
            ]
        ]);

        $client->getFamilyApi()->upsert($filenamecode, [
            'attributes'             => array_merge($supported_attr, ['product_info']),
            'attribute_requirements' => [
                'ecommerce' => ['sku'],
                'mobile' => ['sku'],
                'print' =>  ['sku'],
            ],
            'labels'                 => [
                'en_US' => $filename,
                'fr_FR' => $filename,
                'de_DE' => $filename,
            ]
       ]);
      
        $item['product_info'] = $product_info_value;
        $item['categories'] = $filenamecode;
        $item['family'] = $filenamecode;

        try {
            $item = $this->converter->convert($item, $this->getArrayConverterOptions());
        } catch (DataArrayConversionException $e) {
            $this->skipItemFromConversionException($item, $e);
        }

        if (!is_array($item) || !isset($item['values'])) {
            return $item;
        }

        $item['values'] = $this->mediaPathTransformer
            ->transform($item['values'], $this->fileIterator->getDirectoryPath());

        return $item;
    }

   /**
     * {@inheritdoc}
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->fileIterator = null;
    }

    /**
     * Returns the options for array converter. It can be overridden in the sub classes.
     *
     * @return array
     */
    protected function getArrayConverterOptions()
    {
        return [];
    }

    /**
     * @param array                        $item
     * @param DataArrayConversionException $exception
     *
     * @throws InvalidItemException
     * @throws InvalidItemFromViolationsException
     */
    protected function skipItemFromConversionException(array $item, DataArrayConversionException $exception)
    {
        if (null !== $this->stepExecution) {
            $this->stepExecution->incrementSummaryInfo('skip');
        }

        if (null !== $exception->getViolations()) {
            throw new InvalidItemFromViolationsException(
                $exception->getViolations(),
                new FileInvalidItem($item, ($this->stepExecution->getSummaryInfo('item_position'))),
                [],
                0,
                $exception
            );
        }

        throw new InvalidItemException(
            $exception->getMessage(),
            new FileInvalidItem($item, ($this->stepExecution->getSummaryInfo('item_position'))),
            [],
            0,
            $exception
        );
    }

    /**
     * @param int    $countHeaders
     * @param int    $countData
     * @param string $data
     * @param string $filePath
     *
     * @throws InvalidItemException
     */
    protected function checkColumnNumber($countHeaders, $countData, $data, $filePath)
    {
        if ($countHeaders < $countData) {
            throw new InvalidItemException(
                'pim_connector.steps.file_reader.invalid_item_columns_count',
                new FileInvalidItem($data, ($this->stepExecution->getSummaryInfo('item_position'))),
                [
                    '%totalColumnsCount%' => $countHeaders,
                    '%itemColumnsCount%'  => $countData,
                    '%filePath%'          => $filePath,
                    '%lineno%'            => $this->fileIterator->key()
                ]
            );
        }
    }
}