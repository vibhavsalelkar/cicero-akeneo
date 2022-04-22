<?php

namespace Webkul\Magento2GroupProductBundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Connector\Reader\Import\ProductReader as BaseReader;
use Webkul\Magento2GroupProductBundle\Repository\JobDataMappingRepository;
use Webkul\Magento2GroupProductBundle\Traits\JobDataMappingTrait;
use Webkul\Magento2Bundle\Component\OAuthClient;

/**
 * AssociatedProductReader Class reads the assciated products from the Magento2
 */
class AssociatedProductReader extends BaseReader
{
    use JobDataMappingTrait;
    
    /** @var string $magentoProductType */
    protected $magentoProductType = 'simple';
    const MAPPING_TYPE = 'associatedLinks';

    /** @var JobDataMappingRepository $jobDataMappingRepository */
    private $jobDataMappingRepository;

    public function initialize()
    {
        if (!$this->credentials) {
            $this->credentials = $this->connectorService->getCredentials();
        }
        if (!$this->oauthClient) {
            $this->oauthClient = new OAuthClient($this->credentials['authToken'], $this->credentials['hostName']);
        }
        $this->jobDataMappingRepository = $this->em->getRepository('Magento2GroupProductBundle:JobDataMapping');

        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $this->locales = !empty($filters['structure']['locales']) ? $filters['structure']['locales'] : [];
        $this->scope = !empty($filters['structure']['scope']) ? $filters['structure']['scope'] : '';
        $this->storeMapping = $this->connectorService->getStoreMapping();

        foreach ($this->storeMapping as $storeCode => $storeData) {
            if ($storeData['locale'] === $this->defaultLocale) {
                $this->storeCode = $storeCode;
                break;
            }
        }
        
        $this->filterIdentifiers = $this->jobDataMappingRepository->getAllMappingIdentifiersByType(self::MAPPING_TYPE, $this->stepExecution->getJobExecution()->getId());
        if (!empty($this->filterIdentifiers)) {
            $products['items'] = [];
            foreach ($this->filterIdentifiers as $identifier) {
                $products['items'][] = $identifier;
            }
        }
        $items = [];
        if (!empty($products['items'])) {
            $this->productIterator = new \ArrayIterator($products['items']);
            $products = [];

            for ($i = 0; $i<10 ; $i++) {
                if (null === $this->productIterator->current()) {
                    break;
                }
                $products[] = $this->productIterator->current(); //10 .. 490
                $this->productIterator->next();
            }
            
            if ($products) {
                $items = $this->formatData($products);
            }
        }

        $this->items = $items;
        $this->firstRead = false;
    }
    
    public function read()
    {
        if ($this->itemIterator === null && $this->firstRead === false) {
            $this->itemIterator = new \ArrayIterator($this->items);
            $this->firstRead = true;
        }

        $item = $this->itemIterator->current();
        $this->itemIterator->next();
        
        while (empty($item) && $this->productIterator && $this->productIterator->current()) {
            for ($i = 0; $i<10 ; $i++) {
                if (null === $this->productIterator->current()) {
                    break;
                }
                $products[] = $this->productIterator->current(); //10 .. 490
                $this->productIterator->next();
            }

            if ($products) {
                $this->items = $this->formatData($products);
                $this->itemIterator = new \ArrayIterator($this->items);
            }

            if ($this->itemIterator) {
                $item = $this->itemIterator->current();
                $this->itemIterator->next();
            }
        }

        if ($item !== null) {
            $this->stepExecution->incrementSummaryInfo('read');
        }

        
        
        return  $item;
    }

    protected function formatData($products, $parentCode = null)
    {
        $results = [];
        
        foreach ($products as $product) {
            if (empty($product['sku'])) {
                continue;
            }

            $mapping = $this->getJobMappingByIdentifier($product['sku'], self::MAPPING_TYPE);
            if ($mapping) {
                $associatedProducts = $mapping->getExtras();
                if (!empty($associatedProducts)) {
                    foreach ($associatedProducts as $associatedProduct) {
                        $formatedData = $this->getFormatedProductBySKU($associatedProduct);
                        
                        if (empty($formatedData)) {
                            continue;
                        }
                        $results[] = $formatedData;
                    }
                }
            }
        }

        return $results;
    }
}
