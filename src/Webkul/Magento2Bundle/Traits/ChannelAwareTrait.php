<?php

namespace Webkul\Magento2Bundle\Traits;

/**
* channel aware
*/
trait ChannelAwareTrait
{
    private $channelRepo;
    private $defaultCategory = [];

    private function getDefaultCategoryTreeId($parameters)
    {
        $category = $this->getDefaultCategory($parameters);
        $categoryCodes = [];
        foreach ($category as $value) {
            $categoryCodes[] = $value ? $value->getId() : null;
        }
        
        return $categoryCodes;
    }

    private function getDefaultCategoryTreeCode($parameters)
    {
        $category = $this->getDefaultCategory($parameters);
        $categoryCodes = [];
        foreach ($category as $value) {
            $categoryCodes[] = $value ? $value->getCode() : null;
        }
         
        return array_unique($categoryCodes);
    }

    private function getDefaultCategory($parameters)
    {
        if (!$this->defaultCategory && !empty($parameters)) {
            $filters = is_array($parameters) ? $parameters['filters'] : $parameters->get('filters');
            if (!empty($filters['structure']['scope'])) {
                $channelCode = $filters['structure']['scope'];
            } elseif (isset($filters[0]['context']['scope'])) {
                $channelCode = $filters[0]['context']['scope'];
            } else {
                $channelCode = null;
            }

            if ($channelCode) {
                if (is_array($channelCode)) {
                    foreach ($channelCode as $value) {
                        $channel = $this->channelRepo->findOneByIdentifier($value);
                        $this->defaultCategory[] = $channel ? $channel->getCategory() : null;
                    }
                } else {
                    $channel = $this->channelRepo->findOneByIdentifier($channelCode);
                    $this->defaultCategory[] = $channel ? $channel->getCategory() : null;
                }
            }
        }
                
        return $this->defaultCategory;
    }
}
