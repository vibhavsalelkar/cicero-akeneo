<?php

namespace Webkul\Magento2Bundle\Connector\Reader\Import;

use Webkul\Magento2Bundle\Component\OAuthClient;
use Webkul\Magento2Bundle\Connector\Reader\Import\BaseReader;
use Webkul\Magento2Bundle\Services\Magento2Connector;
use Webkul\Magento2Bundle\Traits\DataMappingTrait;
use Webkul\Magento2Bundle\EventListener\AkeneoVersionsCompatibility;

$classLoad = new AkeneoVersionsCompatibility();
$classLoad->checkVersionAndCreateClassAliases();

/**
 * Base Product Reader
 *
 * @author    webkul <support@webkul.com>
 * @copyright 2010-18 Webkul (http://store.webkul.com/license.html)
 */

abstract class BaseProductReader extends BaseReader implements \ItemReaderInterface, \StepExecutionAwareInterface, \InitializableInterface
{
    use DataMappingTrait;

    const PAGE_SIZE = 500;

    const AKENEO_ENTITY_NAME = 'product';

    /** @var ImportService */
    protected $connectorService;

    /** @var StepExecution */
    protected $stepExecution;

    protected $locales;

    protected $scope;

    protected $oauthClient;

    protected $itemIterator;

    protected $storeMapping;

    protected $items;

    protected $firstRead;

    protected $family;

    /** @var \FileStorerInterface */
    protected $storer;

    /** @var \FileInfoRepositoryInterface */
    protected $fileInfoRepository;

    protected $uploadDir;

    protected $familyVariantObject;

    protected $currentPage;

    protected $totalProducts;

    protected $productIterator;

    protected $onlyNewProducts;

    protected $locale;

    public function __construct(
        Magento2Connector $connectorService,
        \Doctrine\ORM\EntityManager $em,
        \FileStorerInterface $storer,
        \FileInfoRepositoryInterface $fileInfoRepository,
        $uploadDir,
        \FamilyVariantController $familyVariantObject
    ) {
        parent::__construct($connectorService, $em);
        $this->storer = $storer;
        $this->fileInfoRepository = $fileInfoRepository;
        $this->uploadDir = $uploadDir->getTempStoragePath();
        $this->familyVariantObject = $familyVariantObject;
    }

    abstract protected function formatData($products);

    public function initialize()
    {
        if (!$this->credentials) {
            $this->credentials = $this->connectorService->getCredentials();
        }
        if (!$this->oauthClient) {
            $this->oauthClient = new OAuthClient($this->credentials['authToken'], $this->credentials['hostName']);
        }

        $this->onlyNewProducts = $this->stepExecution->getJobParameters()->has('new_products') && $this->stepExecution->getJobParameters()->get('new_products') ?? false;
        $this->filterIdentifiers = $this->stepExecution->getJobParameters()->has('filterIdentifiers') ? $this->stepExecution->getJobParameters()->get('filterIdentifiers') : [];
        
        $filters = $this->stepExecution->getJobParameters()->get('filters');
        $this->locales = !empty($filters['structure']['locales']) ? $filters['structure']['locales'] : [];
        $this->scope = !empty($filters['structure']['scope']) ? $filters['structure']['scope'] : '';
        $this->storeMapping = $this->connectorService->getStoreMapping();
        if (!in_array($this->defaultLocale, $this->locales)) {
            $this->stepExecution->addWarning('Invalid Job', [], new \DataInvalidItem([$this->defaultLocale. ' default store view locale is not added in job']));
            $this->stepExecution->setTerminateOnly();
        }
    
        foreach ($this->storeMapping as $storeCode => $storeData) {
            if ($storeCode == 'allStoreView') {
                $this->storeCode = 'all';
                $this->locale = $storeData['locale'];
            }
        }

        $this->currentPage = 1;
        //Filter products
        if (!empty($this->filterIdentifiers)) {
            $products['items'] = [];

            if (is_array($this->filterIdentifiers)) {
                $this->filterIdentifiers = array_unique($this->filterIdentifiers);
                foreach ($this->filterIdentifiers as $identifier) {
                    $identifier = trim($identifier);
                    $products['items'][] = [
                        'sku' => $identifier
                    ];
                }
            }
        } else {
            $products = $this->getProducts($this->currentPage);
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
        
        while (
            empty($item)
            && $this->totalProducts
            && (($this->currentPage * self::PAGE_SIZE <= $this->totalProducts) || $this->productIterator->current())
         ) {
            if (null === $this->itemIterator->current()) {
                $products = [];
                if (null === $this->productIterator->current() && empty($this->filterIdentifiers)) {
                    $this->currentPage++;
                    $products = $this->getProducts($this->currentPage);
                    if (!empty($products['items'])) {
                        $this->productIterator = new \ArrayIterator($products['items']);
                    }
                }

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
}
