<?php

namespace Webkul\Magento2Bundle\Entity;

/**
 * MappedAttribute
 */
class MappedAttribute
{
    /**
     * @var integer
     */
    private $id;

    /**
     * @var string
     */
    private $magentoCode;

    /**
     * @var string
     */
    private $akeneoCode;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set magentoCode
     *
     * @param string $magentoCode
     *
     * @return MappedAttribute
     */
    public function setMagentoCode($magentoCode)
    {
        $this->magentoCode = $magentoCode;

        return $this;
    }

    /**
     * Get magentoCode
     *
     * @return string
     */
    public function getMagentoCode()
    {
        return $this->magentoCode;
    }

    /**
     * Set akeneoCode
     *
     * @param string $akeneoCode
     *
     * @return MappedAttribute
     */
    public function setAkeneoCode($akeneoCode)
    {
        $this->akeneoCode = $akeneoCode;

        return $this;
    }

    /**
     * Get akeneoCode
     *
     * @return string
     */
    public function getAkeneoCode()
    {
        return $this->akeneoCode;
    }
}
