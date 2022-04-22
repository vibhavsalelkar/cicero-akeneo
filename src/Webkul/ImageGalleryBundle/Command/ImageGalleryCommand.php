<?php

namespace Webkul\ImageGalleryBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;

// ContainerAwareCommand
class ImageGalleryCommand extends Command
{
    protected static $defaultName = 'wk-gallery:setup:install';

    protected function configure()
    {
        $this->setName('wk-gallery:setup:install')
            ->setDescription('Install Webkul Image Gallery setup')
            ->setHelp('setup Webkul Image Gallery bundle installation');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $errorFlag = false;

        /* version wise loading */
        if (class_exists('Pim\Bundle\CatalogBundle\Version')) {
            // version 2
            $versionClass = new \Pim\Bundle\CatalogBundle\Version();
        } elseif (class_exists('Akeneo\Platform\CommunityVersion')) {
            // version 3 or later
            $versionClass = new \Akeneo\Platform\CommunityVersion();
        }

        $version = $versionClass::VERSION;
        $this->updateLessFile($version);

        if ($version >= "5.0") {
            $this->runAkeneo5TranslationInstallationCommand($input, $output);
        } else if ($version >= "4.0") {
            $this->runAkeneo4TranslationInstallationCommand($input, $output);
        } else {
            $this->runAkeneoTranslationInstallationCommand($input, $output);
        }

        return 0;
    }

    protected function runAkeneo5TranslationInstallationCommand(InputInterface $input, OutputInterface $output)
    {
        $this->runAkeneoCommonInstallation($input, $output);

        $yarn_pkg = preg_replace("/\r|\n/", "", shell_exec('which yarnpkg || which yarn || echo "yarn"'));
        shell_exec($yarn_pkg . ' run update-extensions');
    }

    protected function runAkeneo4TranslationInstallationCommand(InputInterface $input, OutputInterface $output)
    {
        $this->runAkeneoCommonInstallation($input, $output);
    }

    protected function runAkeneoCommonInstallation(InputInterface $input, OutputInterface $output)
    {
        shell_exec('rm -rf ./var/cache/ && php bin/console cache:warmup;');
        shell_exec('rm -rf public/bundles public/js');
        $this->runCommand(
            'pim:installer:assets',
            [
                '--clean' => null,
                '--symlink'  => null,
            ],
            $output
        );

        $this->runCommand(
            'doctrine:schema:update',
            [
                '--force' => null,
            ],
            $output
        );

        $yarn_pkg = preg_replace("/\r|\n/", "", shell_exec('which yarnpkg || which yarn || echo "yarn"'));

        shell_exec('rm -rf public/css');
        shell_exec($yarn_pkg . ' run less');

        shell_exec('rm -rf public/dist-dev');
        shell_exec($yarn_pkg . ' run webpack-dev');
 
        shell_exec('rm -rf public/dist');
        shell_exec($yarn_pkg . ' run webpack');
    }

    protected function runAkeneoTranslationInstallationCommand(InputInterface $input, OutputInterface $output)
    {
        $this->runCommand(
            'pim:install:asset',
            [
                '--env' => 'prod',
            ],
            $output
        );

        $this->runCommand(
            'assets:install',
            [
                '--symlink' => null,
                '--relative' => null,
            ],
            $output
        );

        $this->runCommand(
            'doctrine:schema:update',
            [
                '--force' => null,
            ],
            $output
        );
        
        $yarn_pkg = preg_replace("/\r|\n/", "", shell_exec('which yarnpkg || which yarn || echo "yarn"'));

        foreach (['node', 'npm', $yarn_pkg] as $program) {
            $result = shell_exec($program . ' --version');
            if (strpos($result, 'not installed') || strpos($result, 'Ask your administrator')) {
                $output->writeln('<error>' . $result . '</error>');
                $errorFlag = true;
            }
        }

        // run yarn webpack
        $result = shell_exec($yarn_pkg . ' run webpack');

        // success
        if (strpos($result, 'Done in') !== false) {
            $output->writeln('<info>' . $result. '</info>');
        // failure
        } else {
            $output->writeln($result);
            $errorFlag = true;
        }
    }

    protected function runCommand($name, array $args, $output)
    {
        $command = $this->getApplication()->find($name);
        $commandInput = new ArrayInput(
            $args
        );
        $command->run($commandInput, $output);
    }

    protected function updateLessFile($version)
    {
        $location = __DIR__ . '/../Resources/public/less/index.less';
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
