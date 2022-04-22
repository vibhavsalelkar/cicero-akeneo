<?php

namespace Webkul\ImageGalleryBundle\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;

class ClassDefinationForCompatibility
{
    public function onKernelRequest(GetResponseEvent $event)
    {
        $this->createClassAliases();
    }

    public function createUserSystem(ConsoleCommandEvent $event)
    {
        $this->createClassAliases();
    }

    public function createClassAliases()
    {
        if (class_exists('Akeneo\Platform\CommunityVersion')) {
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        } elseif (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        }
        
        $version = $versionClass::VERSION;
       
        if (version_compare($version, '5.0', '>=')) {
            $this->akeneoVersion5();
        } elseif (version_compare($version, '3.0', '>')) {
            $this->akeneoVersion3();
        } else {
            $this->akeneoVersion2();
        }
    }

    public function createAlias()
    {
        if (class_exists('Akeneo\Platform\CommunityVersion')) {
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        } elseif (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        }

        $version = $versionClass::VERSION;
        if (version_compare($version, '5.0', '>=')) {
            $this->akeneoVersion5();
        } elseif (version_compare($version, '3.0', '>')) {
            $this->akeneoVersion3();
        } else {
            $this->akeneoVersion2();
        }
    }

    public function akeneoVersion5()
    {
        $AliaseNames = [
            'CategoryExtension'                         =>  'Akeneo\Pim\Enrichment\Bundle\Twigy\CategoryExtension',
            'ConstraintCollectionProviderInterface'     =>  'Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface',
            'JobInterface'                              =>  'Akeneo\Tool\Component\Batch\Job\JobInterface',
            'DefaultValuesProviderInterface'            =>  'Akeneo\Tool\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface',
            'FlushableInterface'                        =>  'Akeneo\Tool\Component\Batch\Item\FlushableInterface',
            'InitializableInterface'                    =>  'Akeneo\Tool\Component\Batch\Item\InitializableInterface',
            'InvalidItemException'                      =>  'Akeneo\Tool\Component\Batch\Item\InvalidItemException',
            'ItemProcessorInterface'                    =>  'Akeneo\Tool\Component\Batch\Item\ItemProcessorInterface',
            'ItemReaderInterface'                       =>  'Akeneo\Tool\Component\Batch\Item\ItemReaderInterface',
            'ItemWriterInterface'                       =>  'Akeneo\Tool\Component\Batch\Item\ItemWriterInterface',
            'JobRepositoryInterface'                    =>  'Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface',
            'StepExecution'                             =>  'Akeneo\Tool\Component\Batch\Model\StepExecution',
            'AbstractStep'                              =>  'Akeneo\Tool\Component\Batch\Step\AbstractStep',
            'StepExecutionAwareInterface'               =>  'Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface',
            'BaseReader'                                =>  'Akeneo\Pim\Enrichment\Component\Category\Connector\Reader\Database\CategoryReader',
            'CategoryRepositoryInterface'               =>  'Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface',
            'ChannelRepository'                         =>  'Akeneo\Channel\Bundle\Doctrine\Repository\ChannelRepository',
            'AbstractReader'                            =>  'Akeneo\Tool\Component\Connector\Reader\Database\AbstractReader',
            'FileInvalidItem'                           =>  'Akeneo\Tool\Component\Batch\Item\FileInvalidItem',
            'ArrayConverterInterface'                   =>  'Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface',
            'DataInvalidItem'                           =>  'Akeneo\Tool\Component\Batch\Item\DataInvalidItem',
            'CollectionFilterInterface'                 =>  'Akeneo\Pim\Enrichment\Bundle\Filter\CollectionFilterInterface',
            'ObjectDetacherInterface'                   =>  'Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface',
            'PimProductProcessor'                       =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Normalization\ProductProcessor',
            'AbstractProcessor'                         =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\MassEdit\AbstractProcessor',
            'AttributeRepositoryInterface'              =>  'Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface',
            'ChannelRepositoryInterface'                =>  'Akeneo\Channel\Component\Repository\ChannelRepositoryInterface',
            'LocaleRepositoryInterface'                 =>  'Akeneo\Channel\Component\Repository\LocaleRepositoryInterface',
            'EntityWithFamilyValuesFillerInterface'     =>  'Akeneo\Pim\Enrichment\Component\Product\ValuesFiller\EntityWithFamilyValuesFillerInterface',
            'BulkMediaFetcher'                          =>  'Akeneo\Tool\Component\Connector\Processor\BulkMediaFetcher',
            'MetricConverter'                           =>  'Akeneo\Pim\Enrichment\Component\Product\Converter\MetricConverter',
            'Operators'                                 =>  'Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators',
            'ProductFilterData'                         =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\ProductFilterData',
            'FileExtension'                             =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\FileExtension',
            'Currency'                                  =>  'Akeneo\Channel\Component\Model\Currency',
            'JobInstance'                               =>  'Akeneo\Tool\Component\Batch\Model\JobInstance',
            'ProductQueryBuilderFactoryInterface'       =>  'Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface',
            'CompletenessManager'                       =>  'Akeneo\Pim\Enrichment\Component\Product\Manager\CompletenessManager',
            'CategoryRepository'                        =>  'Akeneo\Tool\Bundle\ClassificationBundle\Doctrine\ORM\Repository\CategoryRepository',
            'Datasource'                                =>  'Oro\Bundle\PimDataGridBundle\Datasource\Datasource',
            'DatagridRepositoryInterface'               =>  'Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface',
            'MassActionRepositoryInterface'             =>  'Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface',
            'HydratorInterface'                         =>  'Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\HydratorInterface',
            'ObjectFilterInterface'                     =>  'Akeneo\Pim\Enrichment\Bundle\Filter\ObjectFilterInterface',
            'ChannelInterface'                          =>  'Akeneo\Channel\Component\Model\ChannelInterface',
            'JobParameters'                             =>  'Akeneo\Tool\Component\Batch\Job\JobParameters',
            'ProductInterface'                          =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface',
            'ProductModelInterface'                     =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface',
            'FamilyInterface'                           =>  'Akeneo\Pim\Structure\Component\Model\FamilyInterface',
            'JobExecution'                              =>  'Akeneo\Tool\Component\Batch\Model\JobExecution',
            'FamilyController'                          =>  'Akeneo\Pim\Structure\Bundle\Controller\InternalApi\FamilyController',
            'FamilyUpdater'                             =>  'Akeneo\Pim\Structure\Component\Updater\FamilyUpdater',
            'SaverInterface'                            =>  'Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface',
            'BulkSaverInterface'                        =>  'Akeneo\Tool\Component\StorageUtils\Saver\BulkSaverInterface',
            'StorageEvents'                             =>  'Akeneo\Tool\Component\StorageUtils\StorageEvents',
            'FamilyFactory'                             =>  'Akeneo\Pim\Structure\Component\Factory\FamilyFactory',
            'FamilyRepositoryInterface'                 =>  'Akeneo\Pim\Structure\Component\Repository\FamilyRepositoryInterface',
            'FileStorerInterface'                       =>  'Akeneo\Tool\Component\FileStorage\File\FileStorerInterface',
            'FileInfoRepositoryInterface'               =>  'Akeneo\Tool\Component\FileStorage\Repository\FileInfoRepositoryInterface',
            'FileInfoRepository'                        =>  'Akeneo\Tool\Bundle\FileStorageBundle\Doctrine\ORM\Repository\FileInfoRepository',
            'FileStorage'                               =>  'Akeneo\Pim\Enrichment\Component\FileStorage',
            'SimpleFactoryInterface'                    =>  'Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface',
            'RemoverInterface'                          =>  'Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface',
            'ObjectUpdaterInterface'                    =>  'Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface',
            'IdentifiableObjectRepositoryInterface'     =>  'Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface',
            'AttributeFilterInterface'                  =>  'Akeneo\Pim\Enrichment\Component\Product\ProductModel\Filter\AttributeFilterInterface',
            'FilterInterface'                           =>  'Akeneo\Pim\Enrichment\Component\Product\Comparator\Filter\FilterInterface',
            'AbstractTranslation'                       =>  'Akeneo\Tool\Component\Localization\Model\AbstractTranslation',
            'TranslationInterface'                      =>  'Akeneo\Tool\Component\Localization\Model\TranslationInterface',
            'BaseCategoryInterface'                     =>  'Akeneo\Pim\Enrichment\Component\Category\Model\CategoryInterface',
            'BaseCategoryModel'                         =>  'Akeneo\Pim\Enrichment\Component\Category\Model\Category',
            'CategoryInterface'                         =>  'Akeneo\Tool\Component\Classification\Model\CategoryInterface',
            'BaseCategory'                              =>  'Akeneo\Tool\Component\Classification\Model\Category',
            'Locale'                                    =>  'Akeneo\Channel\Component\Model\Locale',
            'CategoryItemsCounterInterface'             =>  'Akeneo\Pim\Enrichment\Bundle\Doctrine\ORM\Counter\CategoryItemsCounterInterface',
            'ValueFactoryInterface'                     =>  'Akeneo\Pim\Enrichment\Component\Product\Factory\Value\ValueFactoryInterface',
            'ValueConverterInterface'                   =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\StandardToFlat\Product\ValueConverter\ValueConverterInterface',
            'AbstractValueConverter'                    =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\StandardToFlat\Product\ValueConverter\AbstractValueConverter',
            'AttributeColumnsResolver'                  =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\FlatToStandard\AttributeColumnsResolver',
            'ValueConverterInterface_F2S'               =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\FlatToStandard\ValueConverter\ValueConverterInterface',
            'AttributeInterface'                        =>  'Akeneo\Pim\Structure\Component\Model\AttributeInterface',
            'AttributeTypes'                            =>  'Akeneo\Pim\Structure\Component\AttributeTypes',
            'FieldProviderInterface'                    =>  'Akeneo\Platform\Bundle\UIBundle\Provider\Field\FieldProviderInterface',
            'AbstractAttributeType'                     =>  'Akeneo\Pim\Structure\Component\AttributeType\AbstractAttributeType',
            'BaseResultRecordHydrator'                  =>  'Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\Orm\ResultRecordHydrator',
            'AbstractAttributeSetter'                   =>  'Akeneo\Pim\Enrichment\Component\Product\Updater\Setter\AbstractAttributeSetter',
            'EntityWithValuesBuilderInterface'          =>  'Akeneo\Pim\Enrichment\Component\Product\Builder\EntityWithValuesBuilderInterface',
            'EntityWithValuesInterface'                 =>  'Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface',
            'ComparatorInterface'                       =>  'Akeneo\Pim\Enrichment\Component\Product\Comparator\ComparatorInterface',
            'AbstractItemMediaWriter'                   =>  'Akeneo\Tool\Component\Connector\Writer\File\AbstractItemMediaWriter',
            'ArchivableWriterInterface'                 =>  'Akeneo\Tool\Component\Connector\Writer\File\ArchivableWriterInterface',
            'BufferFactory'                             =>  'Akeneo\Tool\Component\Buffer\BufferFactory',
            'FlatItemBufferFlusher'                     =>  'Akeneo\Tool\Component\Connector\Writer\File\FlatItemBufferFlusher',
            'FileExporterPathGeneratorInterface'        =>  'Akeneo\Tool\Component\Connector\Writer\File\FileExporterPathGeneratorInterface',
            'FlatItemBuffer'                            =>  'Akeneo\Tool\Component\Connector\Writer\File\FlatItemBuffer',
            'FilesystemProvider'                        =>  'Akeneo\Tool\Component\FileStorage\FilesystemProvider',
            'FileFetcherInterface'                      =>  'Akeneo\Tool\Component\FileStorage\File\FileFetcherInterface',
            'DataArrayConversionException'              =>  'Akeneo\Tool\Component\Connector\Exception\DataArrayConversionException',
            'InvalidItemFromViolationsException'        =>  'Akeneo\Tool\Component\Connector\Exception\InvalidItemFromViolationsException',
            'FileIteratorFactory'                       =>  'Akeneo\Tool\Component\Connector\Reader\File\FileIteratorFactory',
            'FileIteratorInterface'                     =>  'Akeneo\Tool\Component\Connector\Reader\File\FileIteratorInterface',
            'FileReaderInterface'                       =>  'Akeneo\Tool\Component\Connector\Reader\File\FileReaderInterface',
            'FieldsRequirementChecker'                  =>  'Akeneo\Tool\Component\Connector\ArrayConverter\FieldsRequirementChecker',
            'CategoryFilterType'                        =>  'Oro\Bundle\PimFilterBundle\Form\Type\Filter\CategoryFilterType',
            'BaseFilter'                                =>  'Oro\Bundle\PimFilterBundle\Filter\CategoryFilter',
            'BaseFilterExtension'                       =>  'Oro\Bundle\PimDataGridBundle\Extension\Filter\FilterExtension',
            'BaseUserContext'                           =>  'Akeneo\UserManagement\Bundle\Context\UserContext',
            'LocaleInterface'                           =>  'Akeneo\Channel\Component\Model\LocaleInterface',
            'Message'                                   =>  'Akeneo\Platform\Bundle\UIBundle\Flash\Message',
            'BaseCategoryType'                          =>  'Akeneo\Pim\Enrichment\Bundle\Form\Type\CategoryType',
            'AbstractValue'                             =>  'Akeneo\Pim\Enrichment\Component\Product\Model\AbstractValue',
            'ValueInterface'                            =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ValueInterface',
            'UnknownPropertyException'                  =>  'Akeneo\Tool\Component\StorageUtils\Exception\UnknownPropertyException',
            'InvalidObjectException'                    =>  'Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException',
            'InvalidPropertyException'                  =>  'Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyException',
            'InvalidPropertyTypeException'              =>  'Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException',
            'BulkRemoverInterface'                      =>  'Akeneo\Tool\Component\StorageUtils\Remover\BulkRemoverInterface',
            'RemoveEvent'                               =>  'Akeneo\Tool\Component\StorageUtils\Event\RemoveEvent',
            'RemoverInterface'                          =>  'Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface',
            'StorageEvents'                             =>  'Akeneo\Tool\Component\StorageUtils\StorageEvents',
            'FileStorer'                                =>  'Akeneo\Tool\Component\FileStorage\File\FileStorer',
            'FileInfoInterface'                         =>  'Akeneo\Tool\Component\FileStorage\Model\FileInfoInterface',
            'PathGeneratorInterface'                    =>  'Akeneo\Tool\Component\FileStorage\PathGeneratorInterface',
            'ValidatorInterface'                        =>  'Symfony\Component\Validator\Validator\ValidatorInterface',
            'PathGenerator'                             =>  'Akeneo\Tool\Component\FileStorage\PathGenerator',
            'StreamedFileResponse'                      =>  'Akeneo\Tool\Component\FileStorage\StreamedFileResponse',
            'Link'                                      =>  'Akeneo\Tool\Component\Api\Hal\Link',
            'ProductInterface'                          =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface',
            'ExternelApiAttributeRepositoryInterface'   =>  'Akeneo\Pim\Structure\Component\Repository\ExternalApi\AttributeRepositoryInterface',
            'ExternelApiBaseProductNormalizer'                     =>  'Akeneo\Pim\Enrichment\Component\Product\Normalizer\ExternalApi\ProductNormalizer',
            'BaseProductModelNormalizer'                    =>  'Akeneo\Pim\Enrichment\Component\Product\Normalizer\ExternalApi\ProductModelNormalizer',
            'DatagridInterface'                         =>  'Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface',
            'MassActionEvent'                           =>  'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvent',
            'MassActionEvents'                          =>  'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvents',
            'MassActionResponse'                        =>  'Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse',



        ];
        
        foreach ($AliaseNames as $alias => $aliasPath) {
            if ((interface_exists($aliasPath) || class_exists($aliasPath)) && !class_exists($alias) && !interface_exists($alias)) {
                \class_alias($aliasPath, $alias);
            }
        }
    }

    public function akeneoVersion3()
    {
        $AliaseNames = [
            'CategoryExtension'                         =>  'Akeneo\Pim\Enrichment\Bundle\Twigy\CategoryExtension',
            'ConstraintCollectionProviderInterface'     =>  'Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface',
            'JobInterface'                              =>  'Akeneo\Tool\Component\Batch\Job\JobInterface',
            'DefaultValuesProviderInterface'            =>  'Akeneo\Tool\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface',
            'FlushableInterface'                        =>  'Akeneo\Tool\Component\Batch\Item\FlushableInterface',
            'InitializableInterface'                    =>  'Akeneo\Tool\Component\Batch\Item\InitializableInterface',
            'InvalidItemException'                      =>  'Akeneo\Tool\Component\Batch\Item\InvalidItemException',
            'ItemProcessorInterface'                    =>  'Akeneo\Tool\Component\Batch\Item\ItemProcessorInterface',
            'ItemReaderInterface'                       =>  'Akeneo\Tool\Component\Batch\Item\ItemReaderInterface',
            'ItemWriterInterface'                       =>  'Akeneo\Tool\Component\Batch\Item\ItemWriterInterface',
            'JobRepositoryInterface'                    =>  'Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface',
            'StepExecution'                             =>  'Akeneo\Tool\Component\Batch\Model\StepExecution',
            'AbstractStep'                              =>  'Akeneo\Tool\Component\Batch\Step\AbstractStep',
            'StepExecutionAwareInterface'               =>  'Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface',
            'BaseReader'                                =>  'Akeneo\Pim\Enrichment\Component\Category\Connector\Reader\Database\CategoryReader',
            'CategoryRepositoryInterface'               =>  'Akeneo\Tool\Component\Classification\Repository\CategoryRepositoryInterface',
            'ChannelRepository'                         =>  'Akeneo\Channel\Bundle\Doctrine\Repository\ChannelRepository',
            'AbstractReader'                            =>  'Akeneo\Tool\Component\Connector\Reader\Database\AbstractReader',
            'FileInvalidItem'                           =>  'Akeneo\Tool\Component\Batch\Item\FileInvalidItem',
            'ArrayConverterInterface'                   =>  'Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface',
            'DataInvalidItem'                           =>  'Akeneo\Tool\Component\Batch\Item\DataInvalidItem',
            'CollectionFilterInterface'                 =>  'Akeneo\Pim\Enrichment\Bundle\Filter\CollectionFilterInterface',
            'ObjectDetacherInterface'                   =>  'Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface',
            'PimProductProcessor'                       =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\Normalization\ProductProcessor',
            'AbstractProcessor'                         =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\Processor\MassEdit\AbstractProcessor',
            'AttributeRepositoryInterface'              =>  'Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface',
            'ChannelRepositoryInterface'                =>  'Akeneo\Channel\Component\Repository\ChannelRepositoryInterface',
            'LocaleRepositoryInterface'                 =>  'Akeneo\Channel\Component\Repository\LocaleRepositoryInterface',
            'EntityWithFamilyValuesFillerInterface'     =>  'Akeneo\Pim\Enrichment\Component\Product\ValuesFiller\EntityWithFamilyValuesFillerInterface',
            'BulkMediaFetcher'                          =>  'Akeneo\Tool\Component\Connector\Processor\BulkMediaFetcher',
            'MetricConverter'                           =>  'Akeneo\Pim\Enrichment\Component\Product\Converter\MetricConverter',
            'Operators'                                 =>  'Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators',
            'ProductFilterData'                         =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\ProductFilterData',
            'FileExtension'                             =>  'Akeneo\Pim\Enrichment\Component\Product\Validator\Constraints\FileExtension',
            'Currency'                                  =>  'Akeneo\Channel\Component\Model\Currency',
            'JobInstance'                               =>  'Akeneo\Tool\Component\Batch\Model\JobInstance',
            'ProductQueryBuilderFactoryInterface'       =>  'Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface',
            'CompletenessManager'                       =>  'Akeneo\Pim\Enrichment\Component\Product\Manager\CompletenessManager',
            'CategoryRepository'                        =>  'Akeneo\Tool\Bundle\ClassificationBundle\Doctrine\ORM\Repository\CategoryRepository',
            'Datasource'                                =>  'Oro\Bundle\PimDataGridBundle\Datasource\Datasource',
            'DatagridRepositoryInterface'               =>  'Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface',
            'MassActionRepositoryInterface'             =>  'Oro\Bundle\PimDataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface',
            'HydratorInterface'                         =>  'Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\HydratorInterface',
            'ObjectFilterInterface'                     =>  'Akeneo\Pim\Enrichment\Bundle\Filter\ObjectFilterInterface',
            'ChannelInterface'                          =>  'Akeneo\Channel\Component\Model\ChannelInterface',
            'JobParameters'                             =>  'Akeneo\Tool\Component\Batch\Job\JobParameters',
            'ProductInterface'                          =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface',
            'ProductModelInterface'                     =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface',
            'FamilyInterface'                           =>  'Akeneo\Pim\Structure\Component\Model\FamilyInterface',
            'JobExecution'                              =>  'Akeneo\Tool\Component\Batch\Model\JobExecution',
            'FamilyController'                          =>  'Akeneo\Pim\Structure\Bundle\Controller\InternalApi\FamilyController',
            'FamilyUpdater'                             =>  'Akeneo\Pim\Structure\Component\Updater\FamilyUpdater',
            'SaverInterface'                            =>  'Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface',
            'BulkSaverInterface'                        =>  'Akeneo\Tool\Component\StorageUtils\Saver\BulkSaverInterface',
            'StorageEvents'                             =>  'Akeneo\Tool\Component\StorageUtils\StorageEvents',
            'FamilyFactory'                             =>  'Akeneo\Pim\Structure\Component\Factory\FamilyFactory',
            'FamilyRepositoryInterface'                 =>  'Akeneo\Pim\Structure\Component\Repository\FamilyRepositoryInterface',
            'FileStorerInterface'                       =>  'Akeneo\Tool\Component\FileStorage\File\FileStorerInterface',
            'FileInfoRepositoryInterface'               =>  'Akeneo\Tool\Component\FileStorage\Repository\FileInfoRepositoryInterface',
            'FileInfoRepository'                        =>  'Akeneo\Tool\Bundle\FileStorageBundle\Doctrine\ORM\Repository\FileInfoRepository',
            'FileStorage'                               =>  'Akeneo\Pim\Enrichment\Component\FileStorage',
            'SimpleFactoryInterface'                    =>  'Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface',
            'RemoverInterface'                          =>  'Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface',
            'ObjectUpdaterInterface'                    =>  'Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface',
            'IdentifiableObjectRepositoryInterface'     =>  'Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface',
            'AttributeFilterInterface'                  =>  'Akeneo\Pim\Enrichment\Component\Product\ProductModel\Filter\AttributeFilterInterface',
            'FilterInterface'                           =>  'Akeneo\Pim\Enrichment\Component\Product\Comparator\Filter\FilterInterface',
            'AbstractTranslation'                       =>  'Akeneo\Tool\Component\Localization\Model\AbstractTranslation',
            'TranslationInterface'                      =>  'Akeneo\Tool\Component\Localization\Model\TranslationInterface',
            'BaseCategoryInterface'                     =>  'Akeneo\Tool\Component\Classification\Model\CategoryInterface',
            'BaseCategoryModel'                         =>  'Akeneo\Tool\Component\Classification\Model\Category',
            'CategoryInterface'                         =>  'Akeneo\Tool\Component\Classification\Model\CategoryInterface',
            'BaseCategory'                              =>  'Akeneo\Tool\Component\Classification\Model\Category',
            'Locale'                                    =>  'Akeneo\Channel\Component\Model\Locale',
            'CategoryItemsCounterInterface'             =>  'Akeneo\Pim\Enrichment\Bundle\Doctrine\ORM\Counter\CategoryItemsCounterInterface',
            'ValueFactoryInterface'                     =>  'Akeneo\Pim\Enrichment\Component\Product\Factory\Value\ValueFactoryInterface',
            'ValueConverterInterface'                   =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\StandardToFlat\Product\ValueConverter\ValueConverterInterface',
            'AbstractValueConverter'                    =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\StandardToFlat\Product\ValueConverter\AbstractValueConverter',
            'AttributeColumnsResolver'                  =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\FlatToStandard\AttributeColumnsResolver',
            'ValueConverterInterface_F2S'               =>  'Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\FlatToStandard\ValueConverter\ValueConverterInterface',
            'AttributeInterface'                        =>  'Akeneo\Pim\Structure\Component\Model\AttributeInterface',
            'AttributeTypes'                            =>  'Akeneo\Pim\Structure\Component\AttributeTypes',
            'FieldProviderInterface'                    =>  'Akeneo\Platform\Bundle\UIBundle\Provider\Field\FieldProviderInterface',
            'AbstractAttributeType'                     =>  'Akeneo\Pim\Structure\Component\AttributeType\AbstractAttributeType',
            'BaseResultRecordHydrator'                  =>  'Oro\Bundle\PimDataGridBundle\Datasource\ResultRecord\Orm\ResultRecordHydrator',
            'AbstractAttributeSetter'                   =>  'Akeneo\Pim\Enrichment\Component\Product\Updater\Setter\AbstractAttributeSetter',
            'EntityWithValuesBuilderInterface'          =>  'Akeneo\Pim\Enrichment\Component\Product\Builder\EntityWithValuesBuilderInterface',
            'EntityWithValuesInterface'                 =>  'Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface',
            'ComparatorInterface'                       =>  'Akeneo\Pim\Enrichment\Component\Product\Comparator\ComparatorInterface',
            'AbstractItemMediaWriter'                   =>  'Akeneo\Tool\Component\Connector\Writer\File\AbstractItemMediaWriter',
            'ArchivableWriterInterface'                 =>  'Akeneo\Tool\Component\Connector\Writer\File\ArchivableWriterInterface',
            'BufferFactory'                             =>  'Akeneo\Tool\Component\Buffer\BufferFactory',
            'FlatItemBufferFlusher'                     =>  'Akeneo\Tool\Component\Connector\Writer\File\FlatItemBufferFlusher',
            'FileExporterPathGeneratorInterface'        =>  'Akeneo\Tool\Component\Connector\Writer\File\FileExporterPathGeneratorInterface',
            'FlatItemBuffer'                            =>  'Akeneo\Tool\Component\Connector\Writer\File\FlatItemBuffer',
            'FilesystemProvider'                        =>  'Akeneo\Tool\Component\FileStorage\FilesystemProvider',
            'FileFetcherInterface'                      =>  'Akeneo\Tool\Component\FileStorage\File\FileFetcherInterface',
            'DataArrayConversionException'              =>  'Akeneo\Tool\Component\Connector\Exception\DataArrayConversionException',
            'InvalidItemFromViolationsException'        =>  'Akeneo\Tool\Component\Connector\Exception\InvalidItemFromViolationsException',
            'FileIteratorFactory'                       =>  'Akeneo\Tool\Component\Connector\Reader\File\FileIteratorFactory',
            'FileIteratorInterface'                     =>  'Akeneo\Tool\Component\Connector\Reader\File\FileIteratorInterface',
            'FileReaderInterface'                       =>  'Akeneo\Tool\Component\Connector\Reader\File\FileReaderInterface',
            'FieldsRequirementChecker'                  =>  'Akeneo\Tool\Component\Connector\ArrayConverter\FieldsRequirementChecker',
            'CategoryFilterType'                        =>  'Oro\Bundle\PimFilterBundle\Form\Type\Filter\CategoryFilterType',
            'BaseFilter'                                =>  'Oro\Bundle\PimFilterBundle\Filter\CategoryFilter',
            'BaseFilterExtension'                       =>  'Oro\Bundle\PimDataGridBundle\Extension\Filter\FilterExtension',
            'BaseUserContext'                           =>  'Akeneo\UserManagement\Bundle\Context\UserContext',
            'LocaleInterface'                           =>  'Akeneo\Channel\Component\Model\LocaleInterface',
            'Message'                                   =>  'Akeneo\Platform\Bundle\UIBundle\Flash\Message',
            'BaseCategoryType'                          =>  'Akeneo\Pim\Enrichment\Bundle\Form\Type\CategoryType',
            'AbstractValue'                             =>  'Akeneo\Pim\Enrichment\Component\Product\Model\AbstractValue',
            'ValueInterface'                            =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ValueInterface',
            'UnknownPropertyException'                  =>  'Akeneo\Tool\Component\StorageUtils\Exception\UnknownPropertyException',
            'InvalidObjectException'                    =>  'Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException',
            'InvalidPropertyException'                  =>  'Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyException',
            'InvalidPropertyTypeException'              =>  'Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException',
            'BulkRemoverInterface'                      =>  'Akeneo\Tool\Component\StorageUtils\Remover\BulkRemoverInterface',
            'RemoveEvent'                               =>  'Akeneo\Tool\Component\StorageUtils\Event\RemoveEvent',
            'RemoverInterface'                          =>  'Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface',
            'StorageEvents'                             =>  'Akeneo\Tool\Component\StorageUtils\StorageEvents',
            'FileStorer'                                =>  'Akeneo\Tool\Component\FileStorage\File\FileStorer',
            'FileInfoInterface'                         =>  'Akeneo\Tool\Component\FileStorage\Model\FileInfoInterface',
            'PathGeneratorInterface'                    =>  'Akeneo\Tool\Component\FileStorage\PathGeneratorInterface',
            'ValidatorInterface'                        =>  'Symfony\Component\Validator\Validator\ValidatorInterface',
            'PathGenerator'                             =>  'Akeneo\Tool\Component\FileStorage\PathGenerator',
            'StreamedFileResponse'                      =>  'Akeneo\Tool\Component\FileStorage\StreamedFileResponse',
            'Link'                                      =>  'Akeneo\Tool\Component\Api\Hal\Link',
            'ProductInterface'                          =>  'Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface',
            'ExternelApiAttributeRepositoryInterface'   =>  'Akeneo\Pim\Structure\Component\Repository\ExternalApi\AttributeRepositoryInterface',
            'ExternelApiBaseProductNormalizer'                     =>  'Akeneo\Pim\Enrichment\Component\Product\Normalizer\ExternalApi\ProductNormalizer',
            'BaseProductModelNormalizer'                    =>  'Akeneo\Pim\Enrichment\Component\Product\Normalizer\ExternalApi\ProductModelNormalizer',
            'DatagridInterface'                         =>  'Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface',
            'MassActionEvent'                           =>  'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvent',
            'MassActionEvents'                          =>  'Oro\Bundle\PimDataGridBundle\Extension\MassAction\Event\MassActionEvents',
            'MassActionResponse'                        =>  'Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse',



        ];
        
        foreach ($AliaseNames as $alias => $aliasPath) {
            if ((interface_exists($aliasPath) || class_exists($aliasPath)) && !class_exists($alias) && !interface_exists($alias)) {
                \class_alias($aliasPath, $alias);
            }
        }
    }

    public function akeneoVersion2()
    {
        $AliaseNames = [
            
            'ConstraintCollectionProviderInterface'     =>  'Akeneo\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface',
            'JobInterface'                              =>  'Akeneo\Component\Batch\Job\JobInterface',
            'DefaultValuesProviderInterface'            =>  'Akeneo\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface',
            'FlushableInterface'                        =>  'Akeneo\Component\Batch\Item\FlushableInterface',
            'InitializableInterface'                    =>  'Akeneo\Component\Batch\Item\InitializableInterface',
            'InvalidItemException'                      =>  'Akeneo\Component\Batch\Item\InvalidItemException',
            'ItemProcessorInterface'                    =>  'Akeneo\Component\Batch\Item\ItemProcessorInterface',
            'ItemReaderInterface'                       =>  'Akeneo\Component\Batch\Item\ItemReaderInterface',
            'ItemWriterInterface'                       =>  'Akeneo\Component\Batch\Item\ItemWriterInterface',
            'JobRepositoryInterface'                    =>  'Akeneo\Component\Batch\Job\JobRepositoryInterface',
            'StepExecution'                             =>  'Akeneo\Component\Batch\Model\StepExecution',
            'AbstractStep'                              =>  'Akeneo\Component\Batch\Step\AbstractStep',
            'StepExecutionAwareInterface'               =>  'Akeneo\Component\Batch\Step\StepExecutionAwareInterface',
            'BaseReader'                                =>  'Pim\Component\Connector\Reader\Database\CategoryReader',
            'CategoryRepositoryInterface'               =>  'Akeneo\Component\Classification\Repository\CategoryRepositoryInterface',
            'ChannelRepository'                         =>  'Pim\Bundle\CatalogBundle\Doctrine\ORM\Repository\ChannelRepository',
            'AbstractReader'                            =>  'Pim\Component\Connector\Reader\Database\AbstractReader',
            'FileInvalidItem'                           =>  'Akeneo\Component\Batch\Item\FileInvalidItem',
            'ArrayConverterInterface'                   =>  'Pim\Component\Connector\ArrayConverter\ArrayConverterInterface',
            'DataInvalidItem'                           =>  'Akeneo\Component\Batch\Item\DataInvalidItem',
            'CollectionFilterInterface'                 =>  'Pim\Bundle\CatalogBundle\Filter\CollectionFilterInterface',
            'ObjectDetacherInterface'                   =>  'Akeneo\Component\StorageUtils\Detacher\ObjectDetacherInterface',
            'PimProductProcessor'                       =>  'Pim\Component\Connector\Processor\Normalization\ProductProcessor',
            'AbstractProcessor'                         =>  'Pim\Bundle\EnrichBundle\Connector\Processor\AbstractProcessor',
            'AttributeRepositoryInterface'              =>  'Pim\Component\Catalog\Repository\AttributeRepositoryInterface',
            'ChannelRepositoryInterface'                =>  'Pim\Component\Catalog\Repository\ChannelRepositoryInterface',
            'LocaleRepositoryInterface'                =>  'Pim\Component\Catalog\Repository\LocaleRepositoryInterface',
            'EntityWithFamilyValuesFillerInterface'     =>  'Pim\Component\Catalog\ValuesFiller\EntityWithFamilyValuesFillerInterface',
            'BulkMediaFetcher'                          =>  'Pim\Component\Connector\Processor\BulkMediaFetcher',
            'MetricConverter'                           =>  'Pim\Component\Catalog\Converter\MetricConverter',
            'Operators'                                 =>  'Pim\Component\Catalog\Query\Filter\Operators',
            'ProductFilterData'                         =>  'Pim\Component\Connector\Validator\Constraints\ProductFilterData',
            'FileExtension'                             =>  'Pim\Component\Connector\Validator\Constraints\FileExtension',
            'Currency'                                  =>  'Pim\Component\Catalog\Model\CurrencyInterface',
            'JobInstance'                               =>  'Akeneo\Component\Batch\Model\JobInstance',
            'ProductQueryBuilderFactoryInterface'       =>  'Pim\Component\Catalog\Query\ProductQueryBuilderFactoryInterface',
            'CompletenessManager'                       =>  'Pim\Component\Catalog\Manager\CompletenessManager',
            'CategoryRepository'                        =>  'Akeneo\Bundle\ClassificationBundle\Doctrine\ORM\Repository\CategoryRepository',
            'Datasource'                                =>  'Pim\Bundle\DataGridBundle\Datasource\Datasource',
            'DatagridRepositoryInterface'               =>  'Pim\Bundle\DataGridBundle\Doctrine\ORM\Repository\DatagridRepositoryInterface',
            'MassActionRepositoryInterface'             =>  'Pim\Bundle\DataGridBundle\Doctrine\ORM\Repository\MassActionRepositoryInterface',
            'HydratorInterface'                         =>  'Pim\Bundle\DataGridBundle\Datasource\ResultRecord\HydratorInterface',
            'ObjectFilterInterface'                     =>  'Pim\Bundle\CatalogBundle\Filter\ObjectFilterInterface',
            'ChannelInterface'                          =>  'Pim\Component\Catalog\Model\ChannelInterface',
            'JobParameters'                             =>  'Akeneo\Component\Batch\Job\JobParameters',
            'ProductInterface'                          =>  'Pim\Component\Catalog\Model\ProductInterface',
            'ProductModelInterface'                     =>  'Pim\Component\Catalog\Model\ProductModelInterface',
            'FamilyInterface'                           =>  'Pim\Component\Catalog\Model\FamilyInterface',
            'JobExecution'                              =>  'Akeneo\Component\Batch\Model\JobExecution',
            'FamilyController'                          =>  'Pim\Bundle\EnrichBundle\Controller\Rest\FamilyController',
            'FamilyUpdater'                             =>  'Pim\Component\Catalog\Updater\FamilyUpdater',
            'SaverInterface'                            =>  'Akeneo\Component\StorageUtils\Saver\SaverInterface',
            'BulkSaverInterface'                        =>  'Akeneo\Component\StorageUtils\Saver\BulkSaverInterface',
            'StorageEvents'                             =>  'Akeneo\Component\StorageUtils\StorageEvents',
            'FamilyFactory'                             =>  'Pim\Component\Catalog\Factory\FamilyFactory',
            'FamilyRepositoryInterface'                 =>  'Pim\Component\Catalog\Repository\FamilyRepositoryInterface',
            'FileStorerInterface'                       =>  'Akeneo\Component\FileStorage\File\FileStorerInterface',
            'FileInfoRepositoryInterface'               =>  'Akeneo\Component\FileStorage\Repository\FileInfoRepositoryInterface',
            'FileStorage'                               =>  'Pim\Component\Catalog\FileStorage',
            'SimpleFactoryInterface'                    =>  'Akeneo\Component\StorageUtils\Factory\SimpleFactoryInterface',
            'RemoverInterface'                          =>  'Akeneo\Component\StorageUtils\Remover\RemoverInterface',
            'ObjectUpdaterInterface'                    =>  'Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface',
            'IdentifiableObjectRepositoryInterface'     =>  'Akeneo\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface',
            'AttributeFilterInterface'                  =>  'Pim\Component\Catalog\ProductModel\Filter\AttributeFilterInterface',
            'FilterInterface'                           =>  'Pim\Component\Catalog\Comparator\Filter\FilterInterface',
            'AbstractTranslation'                       =>  'Akeneo\Component\Localization\Model\AbstractTranslation',
            'TranslationInterface'                      =>  'Akeneo\Component\Localization\Model\TranslationInterface',
            'BaseCategoryInterface'                     =>  'Akeneo\Component\Classification\Model\CategoryInterface',
            'BaseCategoryModel'                         =>  'Akeneo\Component\Classification\Model\Category',
            'CategoryInterface'                         =>  'Akeneo\Component\Classification\Model\CategoryInterface',
            'BaseCategory'                              =>  'Akeneo\Component\Classification\Model\Category',
            'Locale'                                    =>  'Pim\Bundle\CatalogBundle\Entity\Locale',
            'CategoryItemsCounterInterface'             =>  'Pim\Bundle\EnrichBundle\Doctrine\Counter\CategoryItemsCounterInterface',
            'ValueFactoryInterface'                     =>  'Pim\Component\Catalog\Factory\Value\ValueFactoryInterface',
            'ValueConverterInterface'                   =>  'Pim\Component\Connector\ArrayConverter\StandardToFlat\Product\ValueConverter\ValueConverterInterface',
            'AbstractValueConverter'                    =>  'Pim\Component\Connector\ArrayConverter\StandardToFlat\Product\ValueConverter\AbstractValueConverter',
            'AttributeColumnsResolver'                  =>  'Pim\Component\Connector\ArrayConverter\FlatToStandard\Product\AttributeColumnsResolver',
            'ValueConverterInterface_F2S'               =>  'Pim\Component\Connector\ArrayConverter\FlatToStandard\Product\ValueConverter\ValueConverterInterface',
            'AttributeInterface'                        =>  'Pim\Component\Catalog\Model\AttributeInterface',
            'AttributeTypes'                            =>  'Pim\Component\Catalog\AttributeTypes',
            'FieldProviderInterface'                    =>  'Pim\Bundle\EnrichBundle\Provider\Field\FieldProviderInterface',
            'AbstractAttributeType'                     =>  'Pim\Bundle\CatalogBundle\AttributeType\AbstractAttributeType',
            'BaseResultRecordHydrator'                  =>  'Pim\Bundle\DataGridBundle\Datasource\ResultRecord\Orm\ResultRecordHydrator',
            'AbstractAttributeSetter'                   =>  'Pim\Component\Catalog\Updater\Setter\AbstractAttributeSetter',
            'EntityWithValuesBuilderInterface'          =>  'Pim\Component\Catalog\Builder\EntityWithValuesBuilderInterface',
            'EntityWithValuesInterface'                 =>  'Pim\Component\Catalog\Model\EntityWithValuesInterface',
            'ComparatorInterface'                       =>  'Pim\Component\Catalog\Comparator\ComparatorInterface',
            'AbstractItemMediaWriter'                   =>  'Akeneo\Tool\Component\Connector\Writer\File\AbstractItemMediaWriter',
            'ArchivableWriterInterface'                 =>  'Pim\Component\Connector\Writer\File\ArchivableWriterInterface',
            'BufferFactory'                             =>  'Akeneo\Component\Buffer\BufferFactory',
            'FlatItemBufferFlusher'                     =>  'Pim\Component\Connector\Writer\File\FlatItemBufferFlusher',
            'FileExporterPathGeneratorInterface'        =>  'Pim\Component\Connector\Writer\File\FileExporterPathGeneratorInterface',
            'FlatItemBuffer'                            =>  'Pim\Component\Connector\Writer\File\FlatItemBuffer',
            'FilesystemProvider'                        =>  'Akeneo\Component\FileStorage\FilesystemProvider',
            'FileFetcherInterface'                      =>  'Akeneo\Component\FileStorage\File\FileFetcherInterface',
            'DataArrayConversionException'              =>  'Pim\Component\Connector\Exception\DataArrayConversionException',
            'InvalidItemFromViolationsException'        =>  'Pim\Component\Connector\Exception\InvalidItemFromViolationsException',
            'FileIteratorFactory'                       =>  'Pim\Component\Connector\Reader\File\FileIteratorFactory',
            'FileIteratorInterface'                     =>  'Pim\Component\Connector\Reader\File\FileIteratorInterface',
            'FileReaderInterface'                       =>  'Pim\Component\Connector\Reader\File\FileReaderInterface',
            'FieldsRequirementChecker'                  =>  'Pim\Component\Connector\ArrayConverter\FieldsRequirementChecker',
            'CategoryFilterType'                        =>  'Pim\Bundle\FilterBundle\Form\Type\Filter\CategoryFilterType',
            'BaseFilter'                                =>  'Pim\Bundle\FilterBundle\Filter\CategoryFilter',
            'BaseFilterExtension'                       =>  'Pim\Bundle\DataGridBundle\Extension\Filter\FilterExtension',
            'BaseUserContext'                           =>  'Pim\Bundle\UserBundle\Context\UserContext',
            'LocaleInterface'                           =>  'Pim\Component\Catalog\Model\LocaleInterface',
            'Message'                                   =>  'Pim\Bundle\EnrichBundle\Flash\Message',
            'BaseCategoryType'                          =>  'Pim\Bundle\EnrichBundle\Form\Type\CategoryType',
            'AbstractValue'                             =>  'Pim\Component\Catalog\Model\AbstractValue',
            'ValueInterface'                            =>  'Pim\Component\Catalog\Model\ValueInterface',
            'UnknownPropertyException'                  =>  'Akeneo\Component\StorageUtils\Exception\UnknownPropertyException',
            'InvalidObjectException'                    =>  'Akeneo\Component\StorageUtils\Exception\InvalidObjectException',
            'InvalidPropertyException'                  =>  'Akeneo\Component\StorageUtils\Exception\InvalidPropertyException',
            'InvalidPropertyTypeException'              =>  'Akeneo\Component\StorageUtils\Exception\InvalidPropertyTypeException',
            'BulkRemoverInterface'                      =>  'Akeneo\Component\StorageUtils\Remover\BulkRemoverInterface',
            'RemoveEvent'                               =>  'Akeneo\Component\StorageUtils\Event\RemoveEvent',
            'RemoverInterface'                          =>  'Akeneo\Component\StorageUtils\Remover\RemoverInterface',
            'StorageEvents'                             =>  'Akeneo\Component\StorageUtils\StorageEvents',
            'FileStorer'                                =>  'Akeneo\Component\FileStorage\File\FileStorer',
            'FileInfoInterface'                         =>  'Akeneo\Component\FileStorage\Model\FileInfoInterface',
            'PathGeneratorInterface'                    =>  'Akeneo\Component\FileStorage\PathGeneratorInterface',
            'StreamedFileResponse'                      =>  'Akeneo\Component\FileStorage\StreamedFileResponse',
            'Link'                                      =>  'Pim\Component\Api\Hal\Link',
            'ProductInterface'                          =>  'Pim\Component\Catalog\Model\ProductInterface',
            'ExternelApiAttributeRepositoryInterface'   =>  'Pim\Component\Api\Repository\AttributeRepositoryInterface',
            'ExternelApiBaseProductNormalizer'                     =>  'Pim\Component\Api\Normalizer\ProductNormalizer',
            'BaseProductModelNormalizer'                =>  'Pim\Component\Api\Normalizer\ProductModelNormalizer',
            'DatagridInterface'                         =>  'Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface',
            'MassActionEvent'                           =>  'Pim\Bundle\DataGridBundle\Extension\MassAction\Event\MassActionEvent',
            'MassActionEvents'                          =>  'Pim\Bundle\DataGridBundle\Extension\MassAction\Event\MassActionEvents',
            'MassActionResponse'                        =>  'Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionResponse',

        ];

        foreach ($AliaseNames as $alias => $aliasPath) {
            if ((interface_exists($aliasPath) || class_exists($aliasPath)) && !class_exists($alias) && !interface_exists($alias)) {
                \class_alias($aliasPath, $alias);
            }
        }
    }
}
