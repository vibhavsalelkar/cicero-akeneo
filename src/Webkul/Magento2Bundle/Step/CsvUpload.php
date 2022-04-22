<?php

namespace Webkul\Magento2Bundle\Step;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();
/**
 * magento2 step implementation that read items, process them and write them using api, code in respective files
 *
 */
class CsvUpload extends \AbstractStep
{
    const STEP_NAME_PRODUCT_EXPORT = 'csv_upload';

    const HEADER_ROW_COUNT = 1;

    /** @var \StepExecution */
    protected $stepExecution = null;

    protected $connectorService = null;

    /**
     * @param string                   $name
     * @param EventDispatcherInterface $eventDispatcher
     * @param \JobRepositoryInterface   $jobRepository
     */
    public function __construct(
        $name,
        EventDispatcherInterface $eventDispatcher,
        \JobRepositoryInterface $jobRepository,
        $connectorService
    ) {
        parent::__construct($name, $eventDispatcher, $jobRepository);
        $this->connectorService = $connectorService;
    }


    /**
     * {@inheritdoc}
     */
    public function doExecute(\StepExecution $stepExecution)
    {
        if ($stepExecution->getJobParameters()->has('disableCsvUpload') && $stepExecution->getJobParameters()->get('disableCsvUpload')) {
            $stepExecution->addSummaryInfo('info', 'remote upload not enabled.');
            return;
        }

        $this->connectorService->setStepExecution($stepExecution);
        $credentials = $this->connectorService->getCredentials();
        
        if (!property_exists($stepExecution->getJobParameters(), 'finalFilePath')) {
            $stepExecution->addWarning('no data to export', [], new \DataInvalidItem([]));
            return;
        }

        $csvPath = $stepExecution->getJobParameters()->finalFilePath;
        $multiValueSeparator = $stepExecution->getJobParameters()->has('multiValueSeparator') ? $stepExecution->getJobParameters()->get('multiValueSeparator') : ',';
        $exportResponse = '';
        $storeViews = 0;
        $csvUploadError = false;

        if (isset($credentials['storeMapping']) && is_array($credentials['storeMapping'])) {
            foreach ($credentials['storeMapping'] as $store) {
                if (!empty($store['locale'])) {
                    $storeViews++;
                }
            }
        }

        if ($storeViews && $storeViews > 1) {
            $inc = 200 - 200 % $storeViews;
        } else {
            $inc = 120;
        }

        if (isset($credentials['hostName']) && $csvPath) {
            $row = 0;
            if (($fp = fopen($csvPath, "r")) !== false) {
                $row = 1;
                while (($record = fgetcsv($fp, 0, ';', '"')) !== false) {
                    $row++;
                }
            }
            $route = trim($credentials['hostName'], '/') . '/rest/V1/process-csv';
            
            if ($row) {
                for ($start = 1; $start<= $row; $start = $start + $inc) {
                    $data = [
                            'data' => [
                                'delimiter' => $stepExecution->getJobParameters()->get('delimiter'),
                                'enclosure' => $stepExecution->getJobParameters()->get('enclosure'),
                                'multi_value_seperator' => $multiValueSeparator,
                                'path' => $this->connectorService->generateUrl(
                                    'webkul_magento2_connector_configuration_get_file',
                                    [
                                                'start' => $start,
                                                'end' => $start + $inc,
                                                'path' => $csvPath
                                            ],
                                    UrlGeneratorInterface::ABSOLUTE_URL
                                ),
                            ]
                        ];
                    $response = $this->requestByCurl(
                        $route,
                        'POST',
                        [
                            'Authorization: Bearer ' . $credentials['authToken'],
                            'Content-Type: application/json'
                        ],
                        $data
                    );
                    if (strpos($response, '<html>') === false) {
                        if (! (
                            strpos($response, "total errors: 0")
                                || strpos($response, "Gesamtfehler: 0")
                                || strpos($response, "totalement des erreurs: 0")
                                || strpos($response, "totale fouten: 0")
                                || strpos($response, "totaal aantal fouten: 0")
                        )) {
                            $response = str_replace('\n', ' || ', strip_tags($response));
                            $response = implode('||', array_unique(explode('||', $response)));

                            $csvUploadError = true;
                            $stepExecution->addWarning(
                                'Upload CSV failed. Please fix the following errors and run the job again.',
                                [$response],
                                new \DataInvalidItem([
                                    'CSV File Start Row From' => $start + self::HEADER_ROW_COUNT ,
                                    'CSV File End Row To' => $start + self::HEADER_ROW_COUNT + $inc,
                                    'Error' => $response
                                ])
                            );
                        }
                    } else {
                        $stepExecution->addWarning($response, [], new \DataInvalidItem([]));
                    }
                    
                    // Close all connections to avoid reaching too many connections in the process when booting again later (tests)
                    $this->connectorService->closeDoctrineConnections();
                }
                
                if (!$csvUploadError) {
                    $response = 'The import was successful';

                    $stepExecution->addSummaryInfo(
                        'info',
                        $response
                    );
                }
            }
        }

        if (!($stepExecution->getJobParameters()->has('downloadCsvFile') && $stepExecution->getJobParameters()->get('downloadCsvFile'))
            && $csvPath
            && file_exists($csvPath)) {
            //unlink($csvPath);
        }
    }

    /**
    * returns curl response for given route
    *
    * @param string $url
    * @param string $method like GET, POST
    * @param array headers (optional)
    *
    * @return string $response
    */
    protected function requestByCurl($url, $method, $headers, $payload = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1800);

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($payload) ? json_encode($payload) : $payload);
        }
        $response = curl_exec($ch);

        return $response;
    }
}
