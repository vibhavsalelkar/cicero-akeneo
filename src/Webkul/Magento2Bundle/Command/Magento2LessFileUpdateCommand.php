<?php

namespace Webkul\Magento2Bundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class Magento2LessFileUpdateCommand extends Command
{
    protected static $defaultName = 'magento2:lessfile:update';

    protected function configure()
    {
        $this
            ->setDescription('Update Magento2 Akeneo connector Less File according to the Version')
            ->setHelp('update the directory web/public in less');
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            // version 2
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        } elseif (class_exists('Akeneo\Platform\CommunityVersion')) {
            // version 3 or later
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        }
        
        $version = $versionClass::VERSION;
        $this->updateLessFile($version);

        $output->writeln('<info> Updated Less file </info>');
        return 0;
    }

    protected function updateLessFile($version)
    {
        $location = __DIR__.'/../Resources/public/less/index.less';
        if (!is_writable($location)) {
            throw new \Exception(sprintf('%s file is not writable, provide the write permission', $location));
        }
        $conf = file_get_contents($location);

        if ($version >= "4.0") {
            $searchValue = './web';
            $replaceValue = './public';
        } else {
            $searchValue = './public';
            $replaceValue = './web';
        }

        $value = str_replace($searchValue, $replaceValue, $conf);
        file_put_contents($location, $value);
    }
}
