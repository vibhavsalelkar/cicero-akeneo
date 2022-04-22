<?php
namespace Webkul\Magento2Bundle\Services;

class CategoryImageDescription
{
    const DESCRIPTION_CODE  = 'description';

    public function setCategoryAdditionalInformation($item, $locale)
    {
        $data = [];

        if (isset($item['description'])) {
            $descriptionLocaleData = $item['description'][$locale] ?? '';
            $data [] = [
                'attribute_code' => self::DESCRIPTION_CODE,
                'value' => $descriptionLocaleData
            ];
        }

        if (isset($item['image'])) {
            //will implement in futher module version;
        }
        
        return $data;
    }
}
