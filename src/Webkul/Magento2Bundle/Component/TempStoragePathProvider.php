<?php

namespace Webkul\Magento2Bundle\Component;

class TempStoragePathProvider
{
    protected $tempStoragePath;

    protected $container;

    public function __construct($container)
    {
        $this->container = $container;

        $this->setTempStoragePath();
    }


    public function getTempStoragePath()
    {
        return $this->tempStoragePath;
    }

    public function setTempStoragePath()
    {
        $this->tempStoragePath = sys_get_temp_dir();

        if ($this->container->hasParameter('tmp_storage_dir')) {
            $this->tempStoragePath = $this->container->getParameter('tmp_storage_dir');
        }
    }
}
