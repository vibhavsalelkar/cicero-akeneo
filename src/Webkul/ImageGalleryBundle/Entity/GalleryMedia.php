<?php

namespace Webkul\ImageGalleryBundle\Entity;

/**
 * GalleryMedia
 */
class GalleryMedia
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $filePath;

    /**
     * @var string
     */
    private $originalFilename;

    /**
     * @var \Webkul\ImageGalleryBundle\Entity\Gallery
     */
    private $gallery;


    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set filePath.
     *
     * @param string $filePath
     *
     * @return GalleryMedia
     */
    public function setFilePath($filePath)
    {
        $this->filePath = $filePath;

        return $this;
    }

    /**
     * Get filePath.
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Set originalFilename.
     *
     * @param string $originalFilename
     *
     * @return GalleryMedia
     */
    public function setOriginalFilename($originalFilename)
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    /**
     * Get originalFilename.
     *
     * @return string
     */
    public function getOriginalFilename()
    {
        return $this->originalFilename;
    }

    /**
     * Set gallery.
     *
     * @param \Webkul\ImageGalleryBundle\Entity\Gallery|null $gallery
     *
     * @return GalleryMedia
     */
    public function setGallery(\Webkul\ImageGalleryBundle\Entity\Gallery $gallery = null)
    {
        $this->gallery = $gallery;

        return $this;
    }

    /**
     * Get gallery.
     *
     * @return \Webkul\ImageGalleryBundle\Entity\Gallery|null
     */
    public function getGallery()
    {
        return $this->gallery;
    }
    /**
     * @var string|null
     */
    private $description;

    /**
     * @var bool|null
     */
    private $thumbnail = false;

    /**
     * @var string|null
     */
    private $title;


    /**
     * Set description.
     *
     * @param string|null $description
     *
     * @return GalleryMedia
     */
    public function setDescription($description = null)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string|null
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set thumbnail.
     *
     * @param bool|null $thumbnail
     *
     * @return GalleryMedia
     */
    public function setThumbnail($thumbnail = null)
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    /**
     * Get thumbnail.
     *
     * @return bool|null
     */
    public function getThumbnail()
    {
        return $this->thumbnail;
    }

    /**
     * Set title.
     *
     * @param string|null $title
     *
     * @return GalleryMedia
     */
    public function setTitle($title = null)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string|null
     */
    public function getTitle()
    {
        return $this->title;
    }
}
