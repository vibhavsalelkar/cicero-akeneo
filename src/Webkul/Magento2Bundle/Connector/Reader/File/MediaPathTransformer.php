<?php

namespace Webkul\Magento2Bundle\Connector\Reader\File;

use Pim\Component\Connector\Reader\File\MediaPathTransformer as BaseMediaPathTransformer;

/**
* can be used to pass url in imports to Akeneo
*/
class MediaPathTransformer extends BaseMediaPathTransformer
{
    /**
     * Transforms a relative path to absolute. Data must be provided in the pivot format.
     *
     * $item exemple:
     * [
     *   'side_view' => [
     *     [
     *       'locale' => null,
     *       'scope'  => null,
     *       'data'   => 'cat_003.png'
     *     ]
     *   ]
     * ]
     *
     * @param array  $attributeValues An associative array (attribute_code => values)
     * @param string $filePath        The absolute path
     *
     * @return array
     */
    public function transform(array $attributeValues, $filePath)
    {
        $mediaAttributes = $this->attributeRepository->findMediaAttributeCodes();
        foreach ($attributeValues as $code => $values) {
            if (in_array($code, $mediaAttributes)) {
                foreach ($values as $index => $value) {
                    if (isset($value['data'])) {
                        $dataFilePath = $value['data'];
                        if (strpos($dataFilePath, 'http://') === 0 || strpos($dataFilePath, 'https://') === 0) {
                            $url = parse_url($dataFilePath);
                            if (isset($url['path'])) {
                                $path = sys_get_temp_dir() . $url['path'];
                                $newFileDirectory = substr($path, 0, strrpos($path, '/'));
                                if (!is_dir($newFileDirectory)) {
                                    mkdir($newFileDirectory, 0777, true);
                                }
                                if (!file_exists($path)) {
                                    try {
                                        file_put_contents($path, fopen($dataFilePath, 'r'));
                                    } catch (\Exception $e) {
                                        // not found image
                                        $path = $dataFilePath;
                                    }
                                }
                            } else {
                                $path = $dataFilePath;
                            }
                            $attributeValues[$code][$index]['data'] = $path;
                        } else {
                            $attributeValues[$code][$index]['data'] = sprintf(
                                '%s%s%s',
                                $filePath,
                                DIRECTORY_SEPARATOR,
                                $dataFilePath
                            );
                        }
                    }
                }
            }
        }

        return $attributeValues;
    }
}
