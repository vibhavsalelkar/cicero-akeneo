<?php

namespace Webkul\Magento2Bundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;

class CorrectShardTimeoutCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('command:correct:shard_timeout_error')
            ->setDescription('Correct shard timeout error.')
            ->setHelp('Correct shard timeout error.');
    }
    protected $commandExecutor;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $errorFlag = false;
        $result = shell_exec('curl -XDELETE localhost:9200/_all');

        $this->runCommand(
            'akeneo:elasticsearch:reset-indexes',
            [],
            $output
        );


        $this->runCommand(
            'pim:product-model:index',
            [
                '--all' => null,
            ],
            $output
        );
        $this->runCommand(
            'pim:product:index',
            [
                '--all' => null
            ],
            $output
        );
    }

    protected function runCommand($name, array $args, $output)
    {
        $command = $this->getApplication()->find($name);
        $commandInput = new ArrayInput(
            $args
        );
        $command->run($commandInput, $output);
    }
}
