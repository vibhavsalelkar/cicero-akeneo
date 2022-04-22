<?php
namespace Webkul\Magento2Bundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateHostCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('magento2:data_mapping:host')
            ->setDescription('Resets host in mapping')
            ->addArgument('host_name', InputArgument::REQUIRED, 'Please enter host_name')
            ->setHelp(
                <<<EOT
                    The <info>%command.name%</info>command reset Host in mapping.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $host_name = $input->getArgument('host_name');
        $em = $container->get('doctrine.orm.entity_manager');
        $configRepo = $container->get('pim_enrich.repository.magento2_credentials');
        $allResult = $configRepo->findAll();

        foreach ($allResult as $result) {
            $configData = $configRepo->findOneById($result->getId());
            $resources = json_decode($result->getResources(), true);
            $resources['host'] = $host_name;
            $configData->setResources(json_encode($resources, true));
            $em->persist($configData);
            $em->flush();
        }

        $output->writeln('Resource host updated');
    }
}
