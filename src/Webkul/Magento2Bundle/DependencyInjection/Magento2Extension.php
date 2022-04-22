<?php

namespace Webkul\Magento2Bundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class Magento2Extension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        /* version wise loading */
        if (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            // version 2
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        } elseif (class_exists('Akeneo\Platform\CommunityVersion')) {
            // version 3 or later
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        }

        $version = $versionClass::VERSION;
        $versionDirectoryPrefix = '2.x/';
        if ($version >= '5.0') {
            $versionDirectoryPrefix = '5.x/';
        } elseif ($version >= "4.0") {
            $versionDirectoryPrefix = '4.x/';
        } elseif ($version >= '3.0') {
            $versionDirectoryPrefix = '3.x/';
        } elseif ($version > '2.3') {
            $versionDirectoryPrefix = '2.3/';
        } elseif ($version > '2.2') {
            $versionDirectoryPrefix = '2.2/';
        }
        
        $loader->load('listeners.yml');
        $loader->load('services.yml');
        $loader->load('controllers.yml');
        $loader->load('data_sources.yml');
        $loader->load('cli_commands.yml');
        $loader->load('media_files.yml');
        $loader->load('job_parameters.yml');

        
        $loader->load('jobs.yml');

        if ($version < '5.0') {
            $loader->load('steps.yml');
        }
        else {
            $loader->load($versionDirectoryPrefix . 'services.yml');
        }
        
        $loader->load('readers.yml');
        $loader->load('processors.yml');
        $loader->load('writers.yml');
        $loader->load('import/jobs.yml');
        $loader->load('import/steps.yml');
        $loader->load('import/readers.yml');
        $loader->load('import/job_parameters.yml');
        $loader->load('import/form_entry.yml');
       
        $loader->load($versionDirectoryPrefix . 'services.yml');
        $loader->load($versionDirectoryPrefix . 'writers.yml');
        $loader->load($versionDirectoryPrefix . 'processors.yml');
        $loader->load($versionDirectoryPrefix . 'jobs.yml');
        $loader->load($versionDirectoryPrefix . 'steps.yml');
        // $loader->load('updaters.yml');
        /* support for https://github.com/mageprince/magento2-productAttachment */
        if ($container->hasParameter('support_magento2_productAttachment') && $container->getParameter('support_magento2_productAttachment')) {
            $loader->load('product_attachment.yml');
        }
        if ($container->hasParameter('support_amasty_productAttachment') && $container->getParameter('support_amasty_productAttachment')) {
            $loader->load('amasty_product_attachment.yml');
        }
        if ($container->hasParameter('support_amasty_product_parts') && $container->getParameter('support_amasty_product_parts')) {
            $loader->load('amasty_product_parts.yml');
        }

        if ($container->hasParameter('support_price_attribute_decimal_place') && $container->getParameter('support_price_attribute_decimal_place')) {
            $loader->load('price_normalizer.yml');
        }
    }
}
