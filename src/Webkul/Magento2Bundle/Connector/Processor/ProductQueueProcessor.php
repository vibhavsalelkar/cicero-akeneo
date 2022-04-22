<?php

namespace Webkul\Magento2Bundle\Connector\Processor;

use Webkul\Magento2Bundle\Connector\Processor\ProductProcessor;

/**
 * Product processor to process and normalize entities to the standard format
 *
 */
class ProductQueueProcessor extends ProductProcessor
{
    protected function convertRelativeUrlToBase64($entry, $productName = '', $position = 0)
    {
        if (is_array($entry)) {
            if (!empty($entry[0]['data'])) {
                $entry = $entry[0]['data'];
            } else {
                return;
            }
        }

        $filename = explode('/', $entry);
        $filename = end($filename);
        try {
            $context = $this->router->getContext();
            $credendial = $this->connectorService->getCredentials();
            if (!empty($credendial['host'])) {
                $context->setHost($credendial['host']);
            }

            if (strpos($entry, '.') !== false) {
                $mimetype = explode('.', $entry);
                $mimetype = end($mimetype);
            } else {
                $mimetype = 'png';
            }
        } catch (\Exception $e) {
            return;
        }

        $convertedItem = [
            'media_type' => 'image',
            'label'      => $productName,
            'position'   => $position,
            'disabled'   => false,
            'types'      => $position ? [] : ["image", "small_image", "thumbnail"],
            'content'    => [
                'base64_encoded_data' => $entry,
                'type'                => $this->guessMimetype($mimetype) ? : 'image/png',
                'name'                => $filename,
            ],
        ];
        
        return $convertedItem;
    }
}
