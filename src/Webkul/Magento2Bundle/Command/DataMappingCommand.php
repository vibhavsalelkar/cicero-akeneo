<?php
namespace Webkul\Magento2Bundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DataMappingCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('magento2:mapping:option-reset')
            ->setDescription('Resets option mapping')
            ->setHelp(
                <<<EOT
                    The <info>%command.name%</info>command reset attribute option mapping.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entityType = 'option';
        $container = $this->getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $qb = $em->createQueryBuilder();
        $query = $qb->delete('Magento2Bundle:DataMapping', 'dm')
                ->andwhere('dm.entityType = :entityType')
                ->setParameter('entityType', $entityType)
                ->getQuery();

        $result = $query->execute();
    }
}
