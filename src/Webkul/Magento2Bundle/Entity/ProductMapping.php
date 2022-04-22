<?php

namespace Webkul\Magento2Bundle\Entity;

/**
 * ProductMapping
 */
class ProductMapping
{
    /**
     * @var int
     */
    private $id;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @var string
     */
    private $sku;

    /**
     * Get sku
     *
     * @return string
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * set sku
     *
     * @var string
     *
     * @return string
     */
    public function setSku($sku)
    {
        $this->sku = $sku;

        return $this;
    }
    /**
     * @var string
     */
    private $type;


    /**
     * Set type
     *
     * @param string $type
     *
     * @return ProductMapping
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
