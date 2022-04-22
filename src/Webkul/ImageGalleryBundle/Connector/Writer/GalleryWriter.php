<?php

namespace Webkul\ImageGalleryBundle\Connector\Writer;

use Webkul\ImageGalleryBundle\Services\ImageGalleryConnector;
use Symfony\Component\Finder\Finder;
use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();

class GalleryWriter extends \AbstractItemMediaWriter implements
    \ItemWriterInterface,
    \InitializableInterface,
    \FlushableInterface,
    \StepExecutionAwareInterface,
    \ArchivableWriterInterface
{
    /** @var \ArrayConverterInterface */
    protected $arrayConverter;

    /** @var \FlatItemBufferFlusher */
    protected $flusher;

    /** @var \BufferFactory */
    protected $bufferFactory;

    /** @var \AttributeRepositoryInterface */
    protected $attributeRepository;

    /** @var \FileExporterPathGeneratorInterface */
    protected $fileExporterPath;

    /** @var string[] */
    protected $mediaAttributeTypes;

    /** @var ConnectorService */
    protected $connectorService;

    /** @var String */
    protected $jobParamFilePath;

    protected $headerAttr;

    protected $filesystemProvider;

    protected $flatTranslator;

    public function __construct(
        \ArrayConverterInterface $arrayConverter,
        \BufferFactory $bufferFactory,
        \FlatItemBufferFlusher $flusher,
        \AttributeRepositoryInterface $attributeRepository,
        \FileExporterPathGeneratorInterface $fileExporterPath,
        array $mediaAttributeTypes,
        ImageGalleryConnector $connectorService,
        \FilesystemProvider $filesystemProvider,
        \FileFetcherInterface $mediaFetcher,
        $flatTranslator = NULL
    ) {
        $versionClass = new \Akeneo\Platform\CommunityVersion();
        $version = $versionClass::VERSION;
        if($version >= "5.0"){
            parent::__construct($arrayConverter, $bufferFactory, $flusher, $attributeRepository, $fileExporterPath, $flatTranslator, $mediaAttributeTypes);
        } else {
            parent::__construct($arrayConverter, $bufferFactory, $flusher, $attributeRepository, $fileExporterPath, $mediaAttributeTypes);
        }
        $this->fileExporterPath = $fileExporterPath;
        $this->connectorService = $connectorService;
        $this->filesystemProvider = $filesystemProvider;
        $this->mediaFetcher = $mediaFetcher;
    }
    /**
     * {@inheritdoc}
     */
    protected function getWriterConfiguration()
    {
        $parameters = $this->stepExecution->getJobParameters();

        return [
            'type'           => 'csv',
            'fieldDelimiter' => $parameters->get('delimiter'),
            'fieldEnclosure' => $parameters->get('enclosure'),
            'shouldAddBOM'   => false,
        ];
    }

    /**
    * {@inheritdoc}
    */
    protected function getItemIdentifier(array $asset)
    {
        return $asset['code'];
    }

    
    /**
     * {@inheritdoc}
     */
    public function write(array $items)
    {
        $parameters = $this->stepExecution->getJobParameters();
        $converterOptions = $this->getConverterOptions($parameters);

        $flatItems = [];
        $directory = $this->stepExecution->getJobExecution()->getExecutionContext()
            ->get(\JobInterface::WORKING_DIRECTORY_PARAMETER);

        foreach ($items as $item) {
            if ($parameters->has('with_media') && $parameters->get('with_media')) {
                $item = $this->resolveMediaPaths($item, $directory);
            }
            
            $convertedArray = $this->resolveAttributeMediaPaths($item, $directory);
            
            $flatItems[] = $convertedArray;
        }

        $parameters = $this->stepExecution->getJobParameters();
        $options = [];
        $options['withHeader'] = $parameters->get('withHeader');

        $this->flatRowBuffer->write($flatItems, $options);
    }


    protected function resolveAttributeMediaPaths($item, $tmpDirectory)
    {
        $media = [];
        $i = 1;
        foreach ($item['media'] as $mediaItem) {
            $identifier = $this->getItemIdentifier($item);

            if (null !== $mediaItem['filePath']) {
                $exportDirectory = $this->fileExporterPath->generate(
                    [
                    'locale' => '',
                    'scope'  => ''
                    ],
                    [
                        'identifier' => $identifier,
                        'code'       => 'media_'.$i,
                    ]
                );
                $imagename = $mediaItem['original_file_name'];
                    
                $filesystem = $this->filesystemProvider->getFilesystem('catalogStorage');
                    
                $mediafiles = str_replace('/tmp/pim/file_storage/', '', $mediaItem['filePath']);
                $this->mediaFetcher->fetch(
                    $filesystem,
                    $mediafiles,
                    ['filePath'=>$tmpDirectory . $exportDirectory,'filename' => $imagename ]
                );
                    
                    
                $finder = new Finder();
                    
                    
                if (is_dir($tmpDirectory . $exportDirectory)) {
                    $files = iterator_to_array($finder->files()->in($tmpDirectory . $exportDirectory));
                      
                    if (!empty($files)) {
                        $path = $exportDirectory . current($files)->getFilename();
                        $this->writtenFiles[$tmpDirectory . $path] = $path;
                        $media[] = preg_replace('#/+#', '/', $path);
                    }
                }
            }
            $i++;
        }

        $stringConvert = implode("&", $media);
        $item['media'] = $stringConvert;

        return $item;
    }
}
