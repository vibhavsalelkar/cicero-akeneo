<?php

namespace Webkul\Magento2Bundle\Connector\Writer\Csv;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();


class ProductWriter implements
\ItemWriterInterface,
\InitializableInterface,
\FlushableInterface,
\StepExecutionAwareInterface,
\ArchivableWriterInterface
{
    protected const DEFAULT_FILE_PATH = 'filePath';

    /** @var ArrayConverterInterface */
    protected $arrayConverter;

    /** @var FlatItemBufferFlusher */
    protected $flusher;

    /** @var BufferFactory */
    protected $bufferFactory;

    /** @var AttributeRepositoryInterface */
    protected $attributeRepository;

    /** @var FileExporterPathGeneratorInterface */
    protected $fileExporterPath;

    /** @var string[] */
    protected $mediaAttributeTypes;

    /** @var StepExecution */
    protected $stepExecution;

    /** @var Filesystem */
    protected $localFs;

    /** @var array */
    protected $writtenFiles = [];

    /** @var FlatItemBuffer */
    protected $flatRowBuffer;

    /** @var string DateTime format for the file path placeholder */
    protected $datetimeFormat = 'Y-m-d_H-i-s';

    /** @var String */
    protected $jobParamFilePath;

    protected $tempPath;

    protected $productModelsExportStarted = false;

    protected $parameters;

    protected $converterOptions;

    protected $shortPathGenerator;

    public function __construct(
        \ArrayConverterInterface $arrayConverter,
        \BufferFactory $bufferFactory,
        \FlatItemBufferFlusher $flusher,
        \AttributeRepositoryInterface $attributeRepository,
        \FileExporterPathGeneratorInterface $fileExporterPath,
        \GenerateFlatHeadersFromFamilyCodesInterface $generateHeadersFromFamilyCodes,
        \GenerateFlatHeadersFromAttributeCodesInterface $generateHeadersFromAttributeCodes,
        $shortPathGenerator,
        array $mediaAttributeTypes,
        string $jobParamFilePath = self::DEFAULT_FILE_PATH
    ) {
        $this->arrayConverter = $arrayConverter;
        $this->bufferFactory = $bufferFactory;
        $this->flusher = $flusher;
        $this->attributeRepository = $attributeRepository;
        $this->mediaAttributeTypes = $mediaAttributeTypes;
        $this->fileExporterPath = $fileExporterPath;
        $this->jobParamFilePath = $jobParamFilePath;
        // $this->flatTranslator = $flatTranslator;

        $this->localFs = new Filesystem();
        $this->shortPathGenerator = $shortPathGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        if (null === $this->flatRowBuffer) {
            $this->flatRowBuffer = $this->bufferFactory->create();
        }

        $exportDirectory = dirname($this->getPath());
        if (!is_dir($exportDirectory)) {
            $this->localFs->mkdir($exportDirectory);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $items)
    {
        if (!$this->parameters) {
            $this->parameters = $this->stepExecution->getJobParameters();
        }

        if (!$this->converterOptions) {
            $this->converterOptions = $this->getConverterOptions($this->parameters);
        }

        $this->arrayConverter->setStepExecution($this->stepExecution);
        
        $flatItems = [];
        $directory = sys_get_temp_dir();
        
        foreach ($items as $item) {
            if ($this->parameters->has('with_media') && $this->parameters->get('with_media')) {
                $item = $this->resolveMediaPaths($item, $directory);
            }
            
            $flatItem = $this->arrayConverter->convert($item, $this->converterOptions);
            

            if (!empty($flatItem)) {
                $flatItems = array_merge($flatItems, (array)$flatItem);
                $this->stepExecution->incrementSummaryInfo('read_products');
                $this->stepExecution->incrementSummaryInfo('read_data.for.store_views.label', count($flatItem));
            }
        }
        
        $options = [];
        $options['withHeader'] = $this->parameters->get('withHeader');
        
        $this->flatRowBuffer->write($flatItems, $options);
        $directory = $this->getTempPath();
        $this->parameters->finalFilePath = $directory;

        $this->negateExtraWrite();
    }

    /**
     * Flush items into a file
     */
    public function flush()
    {
        $this->flusher->setStepExecution($this->stepExecution);

        if (!$this->parameters) {
            $this->parameters = $this->stepExecution->getJobParameters();
        }

        $writtenFiles = $this->flusher->flush(
            $this->flatRowBuffer,
            $this->getWriterConfiguration(),
            $this->getTempPath(),
            ($this->parameters->has('linesPerFile') ? $this->parameters->get('linesPerFile') : -1)
        );

        foreach ($writtenFiles as $writtenFile) {
            $this->writtenFiles[$writtenFile] = basename($writtenFile);
        }

        // $this->exportMedias();
    }
    
    /**
    * @param JobParameters $parameters
    *
    * @return array
    */
    protected function getConverterOptions(\JobParameters $parameters)
    {
        $options = [];

        if ($parameters->has('filters') && isset($parameters->get('filters')['structure']['scope'])) {
            $options['scope'] = $parameters->get('filters')['structure']['scope'];
        }

        if ($parameters->has('with_media')) {
            $options['with_media'] = $parameters->get('with_media');
        }

        if ($parameters->has('decimalSeparator')) {
            $options['decimal_separator'] = $parameters->get('decimalSeparator');
        }

        if ($parameters->has('dateFormat')) {
            $options['date_format'] = $parameters->get('dateFormat');
        }

        if ($parameters->has('ui_locale')) {
            $options['locale'] = $parameters->get('ui_locale');
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    protected function getWriterConfiguration()
    {
        if (!$this->parameters) {
            $this->parameters = $this->stepExecution->getJobParameters();
        }

        return [
            'type'           => 'csv',
            'fieldDelimiter' => $this->parameters->get('delimiter'),
            'fieldEnclosure' => $this->parameters->get('enclosure'),
            'shouldAddBOM'   => false,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemIdentifier(array $product)
    {
        return isset($product['code']) ? $product['code'] : $product['identifier'];
    }

    /**
     * return product type
     */
    protected function getProductType(array $product)
    {
        if (isset($product['code'])) {
            $type = 'configurable';
        } else {
            $type = 'simple';
        }

        return $type;
    }

    /**
     * - Add the download link to data
     *
     * @param array  $item          standard format of an item
     * @param string $tmpDirectory  media files with url to download file
     *
     * @return array
     */
    protected function resolveMediaPaths(array $item, $tmpDirectory)
    {
        $attributeTypes = $this->attributeRepository->getAttributeTypeByCodes(array_keys($item['values']));
        $mediaAttributeTypes = array_filter($attributeTypes, function ($attributeCode) {
            return in_array($attributeCode, $this->mediaAttributeTypes);
        });

        $identifier = $this->getItemIdentifier($item);

        foreach ($mediaAttributeTypes as $attributeCode => $attributeType) {
            foreach ($item['values'][$attributeCode] as $index => $value) {
                if (null !== $value['data'] && $value['data']) {
                    $shortFileKey = $this->shortPathGenerator->generateShortFilePath($value['data']);
                  
                    if ($shortFileKey) {
                        $imageUrl = $this->arrayConverter->generateUrl('webkul_magento2_short_path_media_download', [
                                             'filename' => urlencode($shortFileKey)
                                         ], UrlGeneratorInterface::ABSOLUTE_URL);
                        
                        if ($imageUrl) {
                            $item['values'][$attributeCode][$index]['data'] = $imageUrl;
                        }
                    }
                }
            }
        }

        return $item;
    }

    
    
    
    protected function getTempPath()
    {
        if (!$this->tempPath) {
            $currentDirectory = $this->getRootDirectory();
            $dir = $currentDirectory . DIRECTORY_SEPARATOR . 'tmp';
            
            if (!file_exists($dir)) {
                try {
                    mkdir($dir, 0777, true);
                } catch (\Exception $ex) {
                    throw new \Exception($ex->getMessage());
                }
            }

            $this->tempPath = str_replace(sys_get_temp_dir(), $dir, $this->getPath());
        }

        return $this->tempPath;
    }

    protected function negateExtraWrite()
    {
        /* correct write summary log in same file for product as well as models */
        if ($this->stepExecution->getStepName() === 'product_model_export' && $this->flatRowBuffer->key() && !$this->productModelsExportStarted) {
            $this->stepExecution->incrementSummaryInfo('write', -1*$this->flatRowBuffer->key());
            $this->productModelsExportStarted = true;
        }
    }

    protected $rootDir;

    private function getRootDirectory()
    {
        if (!$this->rootDir) {
            $reflection = new \ReflectionClass('Webkul\Magento2Bundle\Component\OAuthClient');
            $this->rootDir = str_replace(
                '/src/Webkul/Magento2Bundle/Component/OAuthClient.php',
                '',
                $reflection->getFileName()
            );
        }

        return $this->rootDir;
    }

    ////////

    private function fillMissingFlatItemValues(array $items): array
    {
        $additionalHeaders = $this->getAdditionalHeaders();
        $additionalHeadersFilled = array_fill_keys($additionalHeaders, '');

        $flatItemIndex = array_keys($items);
        $additionalHeadersFilledInFlatItemFormat = array_fill_keys($flatItemIndex, $additionalHeadersFilled);

        return array_replace_recursive($additionalHeadersFilledInFlatItemFormat, $items);
    }

    protected function getAdditionalHeaders()
    {
        return [];
    }

    /**
     * Get the file path in which to write the data
     *
     * @param array $placeholders
     *
     * @return string
     */
    public function getPath(array $placeholders = [])
    {
        $parameters = $this->stepExecution->getJobParameters();
        $filePath = $parameters->get($this->jobParamFilePath);

        if (false !== strpos($filePath, '%')) {
            $datetime = $this->stepExecution->getStartTime()->format($this->datetimeFormat);
            $defaultPlaceholders = ['%datetime%' => $datetime, '%job_label%' => ''];
            $jobExecution = $this->stepExecution->getJobExecution();

            if (isset($placeholders['%job_label%'])) {
                $placeholders['%job_label%'] = $this->sanitize($placeholders['%job_label%']);
            } elseif (null !== $jobExecution->getJobInstance()) {
                $defaultPlaceholders['%job_label%'] = $this->sanitize($jobExecution->getJobInstance()->getLabel());
            }
            $replacePairs = array_merge($defaultPlaceholders, $placeholders);
            $filePath = strtr($filePath, $replacePairs);
        }

        return $filePath;
    }

    /**
     * {@inheritdoc}
     */
    public function getWrittenFiles()
    {
        return $this->writtenFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function setStepExecution(\StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

     
    /**
     * Export medias from the working directory to the output expected directory.
     *
     * Basically, we first remove the content of /path/where/my/user/expects/the/export/files/.
     * (This path can exist of an export was launched previously)
     *
     * Then we copy /path/of/the/working/directory/files/ to /path/where/my/user/expects/the/export/files/.
     */
    protected function exportMedias()
    {
        $outputDirectory = dirname($this->getPath());
        $workingDirectory = $this->stepExecution->getJobExecution()->getExecutionContext()
            ->get(\JobInterface::WORKING_DIRECTORY_PARAMETER);

        $outputFilesDirectory = $outputDirectory . DIRECTORY_SEPARATOR . 'files';
        $workingFilesDirectory = $workingDirectory . 'files';

        if ($this->localFs->exists($outputFilesDirectory)) {
            $this->localFs->remove($outputFilesDirectory);
        }

        if ($this->localFs->exists($workingFilesDirectory)) {
            $this->localFs->mirror($workingFilesDirectory, $outputFilesDirectory);
        }
    }

    /**
     * Replace [^A-Za-z0-9\.] from a string by '_'
     *
     * @param string $value
     *
     * @return string
     */
    protected function sanitize($value)
    {
        return preg_replace('#[^A-Za-z0-9\.]#', '_', $value);
    }
}
