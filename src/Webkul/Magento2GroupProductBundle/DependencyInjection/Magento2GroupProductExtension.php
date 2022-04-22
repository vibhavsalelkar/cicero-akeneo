<?php

namespace Webkul\Magento2GroupProductBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class Magento2GroupProductExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('event_listener.yml');
        $loader->load('services.yml');
        $loader->load('writers.yml');
        $loader->load('normalizers.yml');
        
        if (class_exists('Akeneo\Platform\CommunityVersion')) {
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        } elseif (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        }
        $loader->load('steps.yml');
        $loader->load('group_product.yml');
        
        $version = $versionClass::VERSION;
        
        $versionDirectoryPrefix = '2.x/';
        if ($version > '2.2' && $version < '3.0') {
            $versionDirectoryPrefix = '2.x/';

            // load yml
            $loader->load($versionDirectoryPrefix . 'normalizers.yml');
            $loader->load($versionDirectoryPrefix. 'steps.yml');
            $loader->load($versionDirectoryPrefix. 'group_product.yml');
        } elseif ($version > '3.0') {
            $versionDirectoryPrefix = '3.x/';

            // load yml
            $loader->load($versionDirectoryPrefix . 'rowNormalizer.yml');
            $loader->load($versionDirectoryPrefix. 'steps.yml');
            $loader->load($versionDirectoryPrefix. 'group_product.yml');
        }
        
        if($version >= '5.0'){
            $versionDirectoryPrefix = '3.x/';
            $loader->load('5.x/steps.yml');
            $loader->load('5.x/group_product.yml');
            $loader->load($versionDirectoryPrefix . 'rowNormalizer.yml');

        } 
        
    
        if (!class_exists('\Webkul\Magento2Bundle\Component\Version') || $version = new \Webkul\Magento2Bundle\Component\Version() || $version->getModuleVersion() < '1.4.8') {
            return;
        }


        if (class_exists("PimEnterprise\Bundle\EnrichBundle\Provider\Form\ProductFormProvider") || class_exists("Akeneo\Pim\Permission\Bundle\Form\Provider\ProductFormProvider")) {
            $loader->load('normalizers_ee.yml');
        }

        if (class_exists('\Webkul\Magento2Bundle\Component\Version')) {
            $version = new \Webkul\Magento2Bundle\Component\Version();

            if ($version->getModuleVersion() >= '1.4.8') {
                $loader->load('import/jobs.yml');
                $loader->load('import/steps.yml');
                $loader->load('import/readers.yml');
                $loader->load('import/job_parameters.yml');
                $loader->load('import/form_entry.yml');
            }
        }
    }
}
