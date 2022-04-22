<?php

namespace Webkul\Magento2BundleProductBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class Magento2BundleProductExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        
              
        
        if(class_exists('Akeneo\Platform\CommunityVersion')) {
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        } elseif(class_exists('Pim\Bundle\CatalogBundle\Version')) {
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        }
        
        
        $version = $versionClass::VERSION;
        
        $versionDirectoryPrefix = '2.x/';        
        if($version > '2.2' && $version < '3.0') {
            $versionDirectoryPrefix = '2.x/';

            // load yml
            $loader->load($versionDirectoryPrefix . 'normalizers.yml');          
        } elseif ($version > '3.0' && $version < '5.0'){
            $versionDirectoryPrefix = '3.x/';

            // load yml
            $loader->load($versionDirectoryPrefix . 'rowNormalizer.yml');
        } 
        
        if($version >= '5.0'){
            $versionDirectoryPrefix = '3.x/';
            $version5DirectoryPrefix = '5.x/';
            $loader->load($version5DirectoryPrefix . 'processor.yml');
            $loader->load($versionDirectoryPrefix . 'rowNormalizer.yml');
            $loader->load('serializers/standard/'.$version5DirectoryPrefix.'serializers_standard.yml');
            $loader->load($version5DirectoryPrefix . 'services.yml');
            $loader->load($version5DirectoryPrefix . 'event_listener.yml');


        }else{
        $loader->load($versionDirectoryPrefix . 'processor.yml');
        $loader->load('serializers/standard/serializers_standard.yml');
        $loader->load('services.yml');
        $loader->load('event_listener.yml');

        }
        $loader->load($versionDirectoryPrefix . 'parameters.yml');
        $loader->load($versionDirectoryPrefix . 'datagrid_listeners.yml');
        $loader->load($versionDirectoryPrefix . 'data_source.yml');

       
        if(class_exists('\Webkul\Magento2Bundle\Component\Version')) {
            $version = new \Webkul\Magento2Bundle\Component\Version();
            if($version->getModuleVersion() < '1.4.8') {
                return;
            }
        } else {
            return;
        }
    

        
        $loader->load('form_providers.yml');
        $loader->load('entities.yml');
        $loader->load('updater.yml');
        $loader->load('repositories.yml');
        $loader->load('array_converters.yml');
        $loader->load('exports/steps.yml');
        $loader->load('exports/readers.yml');
        $loader->load('data_source.yml');
        $loader->load('pagers.yml');
        $loader->load('extensions.yml');
        

        if(class_exists('Akeneo\Platform\EnterpriseVersion')) {
            $ee = new \Akeneo\Platform\EnterpriseVersion;
            if($ee::VERSION >= '3.0') {
                $loader->load('merger_ee.yml');
            }            
        }

        if(class_exists("PimEnterprise\Bundle\EnrichBundle\Provider\Form\ProductFormProvider") || class_exists("Akeneo\Pim\Permission\Bundle\Form\Provider\ProductFormProvider")) {
            $loader->load('normalizers_ee.yml');    
        } 
        
        if($version->getModuleVersion() >= '1.4.8') {
            $loader->load('import/jobs.yml'); 
            $loader->load('import/steps.yml'); 
            $loader->load('import/readers.yml'); 
            $loader->load('import/job_parameters.yml'); 
            $loader->load('import/form_entry.yml'); 
        }
    }   
}
