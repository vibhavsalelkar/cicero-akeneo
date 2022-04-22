<?php

namespace Webkul\Magento2GroupProductBundle\EventListener;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class AkeneoVersionsCompatibility
{
    /**
     * Create the Version Classes alias on kernal response event.
     * @param GetResponseEvent
     */
    public function onKernalRequest(GetResponseEvent $event)
    {
        $this->checkVersionAndCreateClassAliases();
    }
    
    /**
     * Create the version classes alias on console command event.
     * @param ConsoleCommandEvent
     */
    public function onConsoleCommand(ConsoleCommandEvent $event)
    {
        $this->checkVersionAndCreateClassAliases();
    }

    /**
     * Create the Version Class aliases based on version on Kernal and Console commands event
     * @param GenericEvent
     */
    public function checkVersionAndCreateClassAliases()
    {
        if (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            // version 2
            $akeneoVersionClass = new \Pim\Bundle\CatalogBundle\Version();
        } elseif (class_exists('Akeneo\Platform\CommunityVersion')) {
            // version 3 or later
            $akeneoVersionClass = new \Akeneo\Platform\CommunityVersion();
        }

        $this->createClassesAlieas($akeneoVersionClass::VERSION);
    }

    /**
    * Create the Alisaes based on version
    * @param string
    */
    public function createClassesAlieas(string $version)
    {
        if (version_compare($version, '3.0', '>')) {
            $index = '3.X';
        } else {
            $index = '2.X';
        }
        
        foreach (self::CLASS_ALISASE_NAMES as $alias => $aliasPath) {
            if (
                (interface_exists($aliasPath[$index]) || class_exists($aliasPath[$index]))
                && !interface_exists($alias) && !class_exists($alias)
            ) {
                \class_alias($aliasPath[$index], $alias);
            }
        }
    }
    
   
    const CLASS_ALISASE_NAMES = [
        'AkeneoVersion' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\Version',
            '3.X' => 'Akeneo\Platform\CommunityVersion'
        ],
        'BaseExtension' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Extension\Filter\FilterExtension',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\Extension\Filter\FilterExtension',
        ],
        'BaseDataSource' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Datasource\ProductDatasource',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\Datasource\ProductDatasource',
        ],
        'FilterEntityWithValuesSubscriber' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriber',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriber',
        ],
        'FilterEntityWithValuesSubscriberConfiguration' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriberConfiguration',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriberConfiguration',
        ],
        'EntityWithValuesInterface' => [
            '2.X' => 'Pim\Component\Catalog\Model\EntityWithValuesInterface',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface',
        ],
        'BaseProductNormalizer' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Normalizer\ProductNormalizer',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Normalizer\InternalApi\ProductNormalizer',
        ],
        'ImageNormalizer' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Normalizer\ImageNormalizer',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Normalizer\InternalApi\ImageNormalizer'
        ],
        
        'BaseDatagridProductNormalizer' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Normalizer\ProductNormalizer',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\Normalizer\ProductNormalizer'
        ],
        'ProductModelNormalizer' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Normalizer\ProductModelNormalizer',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\Normalizer\ProductModelNormalizer'
        ],
        'UserContext' => [
            '2.X' => 'Pim\Bundle\UserBundle\Context\UserContext',
            '3.X' => 'Akeneo\UserManagement\Bundle\Context\UserContext'
        ],
        'ProductBuilderInterface' => [
            '2.X' => 'Pim\Component\Catalog\Builder\ProductBuilderInterface',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Builder\ProductBuilderInterface'
        ],

        // magento2 extension classes

        'InvalidObjectException' => [
            '2.X' => 'Akeneo\Component\StorageUtils\Exception\InvalidObjectException',
            '3.X' => 'Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException'
        ],
        'InvalidPropertyTypeException' => [
            '2.X' => 'Pim\Component\Catalog\Validator\Constraints\Channel',
            '3.X' => 'Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException'
        ],
        'AbstractFieldSetter' => [
            '2.X' => 'Pim\Component\Catalog\Updater\Setter\AbstractFieldSetter',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Updater\Setter\AbstractFieldSetter'
        ],
        'BasePropertiesNormalizer' => [
            '2.X' => 'Pim\Component\Catalog\Normalizer\Standard\Product\PropertiesNormalizer',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Normalizer\Standard\Product\PropertiesNormalizer'
        ],
        'BaseProductUpdater' => [
            '2.X' => 'Pim\Component\Catalog\Updater\ProductUpdater',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Updater\ProductUpdater'
        ],
        'StandardToFlatProduct' => [
            '2.X' => 'Pim\Component\Connector\ArrayConverter\StandardToFlat\Product',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\StandardToFlat\Product'
        ],
        'DatabaseProductReader' => [
            '2.X' => 'Pim\Component\Connector\Reader\Database\ProductReader',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Connector\Reader\Database\ProductReader'
        ],
        'OrmProductModelRepository' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\Doctrine\ORM\Repository\ProductModelRepository',
            '3.X' => 'Akeneo\Pim\Enrichment\Bundle\Doctrine\ORM\Repository\ProductModelRepository'
        ],
        'FormProviderInterface' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Provider\Form\FormProviderInterface',
            '3.X' => 'Akeneo\Platform\Bundle\UIBundle\Provider\Form\FormProviderInterface'
        ],
        'BaseProductFormProvider' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Provider\Form\ProductFormProvider',
            '3.X' => 'Akeneo\Pim\Enrichment\Bundle\Provider\Form\ProductFormProvider'
        ],
        'CursorableRepositoryInterface' => [
            '2.X' => 'Akeneo\Component\StorageUtils\Repository\CursorableRepositoryInterface',
            '3.X' => 'Akeneo\Tool\Component\StorageUtils\Repository\CursorableRepositoryInterface'
        ],
        'RemoverInterface' => [
            '2.X' => 'Akeneo\Component\StorageUtils\Remover\RemoverInterface',
            '3.X' => 'Akeneo\Tool\Component\StorageUtils\Remover\RemoverInterface'
        ],
        'AttributeConverterInterface' => [
            '2.X' => 'Pim\Component\Catalog\Localization\Localizer\AttributeConverterInterface',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Localization\Localizer\AttributeConverterInterface'
        ],
        'ConverterInterface' => [
            '2.X' => 'Pim\Component\Enrich\Converter\ConverterInterface',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Converter\ConverterInterface'
        ],
        'ModelProduct' => [
            '2.X' => 'Pim\Component\Catalog\Model\Product',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Model\Product'
        ],
        'AkeneoVersion' => [
            '2.X' => 'Pim\Bundle\CatalogBundle\Version',
            '3.X' => 'Akeneo\Platform\CommunityVersion'
        ],
        'BaseExtension' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Extension\Filter\FilterExtension',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\Extension\Filter\FilterExtension',
        ],
        'BaseDataSource' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Datasource\ProductDatasource',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\Datasource\ProductDatasource',
        ],
        'FilterEntityWithValuesSubscriber' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriber',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriber',
        ],
        'FilterEntityWithValuesSubscriberConfiguration' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriberConfiguration',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\EventSubscriber\FilterEntityWithValuesSubscriberConfiguration',
        ],
        'EntityWithValuesInterface' => [
            '2.X' => 'Pim\Component\Catalog\Model\EntityWithValuesInterface',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface',
        ],
        'BaseProductNormalizer' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Normalizer\ProductNormalizer',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Normalizer\InternalApi\ProductNormalizer',
        ],
        'ImageNormalizer' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Normalizer\ImageNormalizer',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Normalizer\InternalApi\ImageNormalizer'
        ],
        'BaseDatagridProductNormalizer' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Normalizer\ProductNormalizer',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\Normalizer\ProductNormalizer'
        ],
        'ProductModelNormalizer' => [
            '2.X' => 'Pim\Bundle\DataGridBundle\Normalizer\ProductModelNormalizer',
            '3.X' => 'Oro\Bundle\PimDataGridBundle\Normalizer\ProductModelNormalizer'
        ],
        'ProductController' => [
            '2.X' => 'Pim\Bundle\EnrichBundle\Controller\Rest\ProductController',
            '3.X' => 'Akeneo\Pim\Enrichment\Bundle\Controller\InternalApi\ProductController'
        ],
        'UserContext' => [
            '2.X' => 'Pim\Bundle\UserBundle\Context\UserContext',
            '3.X' => 'Akeneo\UserManagement\Bundle\Context\UserContext'
        ],
        'ProductBuilderInterface' => [
            '2.X' => 'Pim\Component\Catalog\Builder\ProductBuilderInterface',
            '3.X' => 'Akeneo\Pim\Enrichment\Component\Product\Builder\ProductBuilderInterface'
        ],
        'CollectionFilterInterface' => [
            '2.X'  =>  'Pim\Bundle\CatalogBundle\Filter\CollectionFilterInterface',
            '3.X'  =>  'Akeneo\Pim\Enrichment\Bundle\Filter\CollectionFilterInterface',
        ],
        'BaseProductFormProviderEE' => [
            '2.X' => 'PimEnterprise\Bundle\EnrichBundle\Provider\Form\ProductFormProvide',
            '3.X' => 'Akeneo\Pim\Permission\Bundle\Form\Provider\ProductFormProvider'
        ]
    ];
}
