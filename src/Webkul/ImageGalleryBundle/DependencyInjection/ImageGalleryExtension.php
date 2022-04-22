<?php

namespace Webkul\ImageGalleryBundle\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * PurgeMediaFilesExtension.php
 *
 * @author      Aman Srivastava <aman.srivastava462@webkul.com>
 * @copyright   2020 Webkul Soft Pvt Ltd.
 */

class ImageGalleryExtension extends Extension
{

    /**
     * {@inheritDoc}
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('controllers.yml');
        $loader->load('event_listener.yml');
        $loader->load('cli_command.yml');
        $loader->load('data_sources.yml');
        $loader->load('repositories.yml');
        $loader->load('attribute_types.yml');
        $loader->load('comparators.yml');
        $loader->load('provider.yml');
        $loader->load('factories.yml');
        $loader->load('updaters.yml');
        $loader->load('jobs.yml');
        $loader->load('steps.yml');
        $loader->load('readers.yml');
        $loader->load('writers.yml');
        $loader->load('serializers_standard.yml');
        $loader->load('processors.yml');
        $loader->load('form_entry.yml');
        $loader->load('job_parameters.yml');
        
        $loader->load('savers.yml');

        if (class_exists('Akeneo\Platform\CommunityVersion')) {
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        } elseif (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        }
        $version = $versionClass::VERSION;

        // if ($version > '3.0') {
        //     $versionDirectoryPrefix = '3.0/';
        //     $loader->load('3.0/services.yml');
        // }
        if ($version > '3.0' && $version < '4.0') {
            $versionDirectoryPrefix = '3.0/';
            $loader->load('3.0/services.yml');
            //$loader->load('3.0/writers.yml');
            // $loader->load('3.0/mass_actions.yml');
            // $loader->load('3.0/controllers.yml');
            // $loader->load('3.0/context.yml');
        } elseif ($version > '4.0' && $version < '5.0') {
            $loader->load('4.0/servicenames.yml');
            // $loader->load('4.0/mass_actions.yml');
            $loader->load('4.0/factories.yml');
            //$loader->load('4.0/writers.yml');
            // $loader->load('4.0/controllers.yml');
            // $loader->load('4.0/services.yml');
            // $loader->load('4.0/context.yml');
        } elseif ($version > '5.0') {
            $loader->load('4.0/servicenames.yml');
            $loader->load('4.0/factories.yml');
            $loader->load('5.0/writers.yml');
        } else {
            $loader->load('2.x/servicenames.yml');
            //$loader->load('2.x/writers.yml');
            // $loader->load('3.0/mass_actions.yml');
        }
    }
}
