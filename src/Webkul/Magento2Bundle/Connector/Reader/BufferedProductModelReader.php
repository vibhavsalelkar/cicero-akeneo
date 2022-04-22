<?php

namespace Webkul\Magento2Bundle\Connector\Reader;

use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

class BufferedProductModelReader extends \AbstractReader implements
    \ItemReaderInterface,
    \StepExecutionAwareInterface
{
    protected $tempDataManager;

    public function __construct($tempDataManager)
    {
        $this->tempDataManager = $tempDataManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function getResults()
    {
        $workingDirectory = $this->stepExecution->getJobExecution()->getExecutionContext()
            ->get(\JobInterface::WORKING_DIRECTORY_PARAMETER);

        $fileName = $this->tempDataManager->getFileName($workingDirectory);
        $result = [];

        
        if (($handle = fopen($fileName, "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "|")) !== false) {
                $result[] = $data;
            }
            fclose($handle);
        } else {
            $this->tempDataManager->removeFile($fileName);
        }

        return new \ArrayIterator($result);
    }
}
