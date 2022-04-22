<?php

namespace Webkul\Magento2Bundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveUnusedFilesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('wk_command:remove_files:unused')
            ->setDescription('Remove unsed files from filesystem')
            ->setHelp('Remove unsed files from filesystem. product images could not be restored from history after that.');
    }

    protected $commandExecutor;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Starting files check.</info>');
        $mediaAttribute = $this->getContainer()->get('pim_catalog.repository.attribute')->findMediaAttributeCodes();

        $usedFiles = [];
        // product query builder factory
        $pqbFactory = $this->getContainer()->get('pim_catalog.query.product_model_query_builder_factory');
        $count = 0;
        // returns a new instance of product query builder
        $pqb = $pqbFactory->create([]);
        $productsCursor = $pqb->execute();
        foreach ($productsCursor as $product) {
            $rawValues = $product->getRawValues();
            foreach ($mediaAttribute as $attribute) {
                if (!empty($rawValues[$attribute]['<all_channels>']['<all_locales>'])) {
                    $val = $rawValues[$attribute]['<all_channels>']['<all_locales>'];
                    if (gettype($val) == 'string') {
                        $usedFiles[] = $val;
                    }
                }
            }
            $output->writeln('Read product model count: ' . (++$count));
        }

        // product query builder factory
        $pqbFactory = $this->getContainer()->get('pim_catalog.query.product_query_builder_factory');
        // returns a new instance of product query builder
        $pqb = $pqbFactory->create([]);
        $productsCursor = $pqb->execute();
        $count = 0;
        foreach ($productsCursor as $product) {
            $rawValues = $product->getRawValues();
            foreach ($mediaAttribute as $attribute) {
                if (!empty($rawValues[$attribute]['<all_channels>']['<all_locales>'])) {
                    $val = $rawValues[$attribute]['<all_channels>']['<all_locales>'];
                    if (gettype($val) == 'string') {
                        $usedFiles[] = $val;
                    }
                }
            }
            $output->writeln('Read product count:' . $count++);
        }

        $dir = $this->getContainer()->getParameter('catalog_storage_dir');
        $di = new \RecursiveDirectoryIterator($dir);
        foreach (new \RecursiveIteratorIterator($di) as $filename => $file) {
            if (is_file($file)) {
                $dirname = $dir . DIRECTORY_SEPARATOR;
                $name = str_replace($dirname, '', $filename);
                if (!in_array($name, $usedFiles)) {
                    $newName = '/tmp/removed-files/'.$name;
                    if (!file_exists(dirname($newName))) {
                        mkdir(dirname($newName), 0777, true);
                    }
                    rename($filename, $newName);
                }
            }
        }
    }
}
