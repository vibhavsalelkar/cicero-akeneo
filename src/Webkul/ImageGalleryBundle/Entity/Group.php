<?php

namespace Webkul\ImageGalleryBundle\Entity;

/**
 * Group
 */
class Group
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $code;


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
     * Set code
     *
     * @param string $code
     *
     * @return Group
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }
}
