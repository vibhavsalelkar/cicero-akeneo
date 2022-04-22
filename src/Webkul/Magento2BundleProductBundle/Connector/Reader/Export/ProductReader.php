<?php

namespace Webkul\Magento2BundleProductBundle\Connector\Reader\Export;

use Webkul\Magento2Bundle\Services\Magento2Connector;

/**
 * Storage-agnostic product reader using the Product Query Builder
 */
class ProductReader extends \DatabaseProductReader
{
    private $container;
    private $productRepo;
    /**
     * @param \ProductQueryBuilderFactoryInterface $pqbFactory
     * @param \ChannelRepositoryInterface          $channelRepository
     * @param \CompletenessManager                 $completenessManager
     * @param \MetricConverter                     $metricConverter
     * @param bool                                $generateCompleteness
     */
    public function __construct(
        \ProductQueryBuilderFactoryInterface $pqbFactory,
        \ChannelRepositoryInterface $channelRepository,
        $completenessManager,
        \MetricConverter $metricConverter,
        $generateCompleteness,
        $container,
        $productRepo
    ) {
        if(\AkeneoVersion::VERSION > 2.3) {
            parent::__construct(
                $pqbFactory,
                $channelRepository,
                $metricConverter,
                $completenessManager,
                $generateCompleteness
            );

        } else {
            parent::__construct(
                $pqbFactory,
                $channelRepository,
                $completenessManager,
                $metricConverter,
                $generateCompleteness
            );
        }
        $this->container = $container;
        $this->productRepo = $productRepo;;
    }

    /**
     * @inheritdoc     
     */
    protected function getProductsCursor(array $filters, \ChannelInterface $channel = null)
    {
        $productRepository = $this->productRepo;
        $qb = $productRepository->createqueryBuilder('p')
                ->select('p.identifier')
                ->leftJoin('p.categories', 'cat')
                ->andWhere('p.bundleOptions IS NOT NULL')
                ->andWhere('cat.code IS NOT NULL');
        $bundleProducts = $qb->getQuery()->getScalarResult();
        
        $bundleProductsSkus = array_column($bundleProducts, "identifier");
        $options = ['filters' => $filters];
        
        if (null !== $channel) {
            $options['default_scope'] = $channel->getCode();
        }
        
        $productQueryBuilder = $this->pqbFactory->create($options);
        $productQueryBuilder->addFilter('sku', \Operators::IN_LIST, $bundleProductsSkus);

        return $productQueryBuilder->execute();
    }
}
