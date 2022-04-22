<?php

namespace Webkul\Magento2BundleProductBundle\Component\Connector\ArrayConverter\FlatToStandard;
$obj = new \Webkul\Magento2BundleProductBundle\Listener\AkeneoVersionsCompatibility();
$obj->checkVersionAndCreateClassAliases();
/**
 * Convert a Product from Flat to Standard structure.
 *
 * This conversion does not result in the standard format. The structure is respected but data are not.
 * Firstly, the data is not delocalized here.
 * Then numeric attributes (metric, price, number) which may contain decimals, are not converted to string but remain float,
 * to be compatible with XLSX files and localization.
 *
 * To get a real standardized from the flat format, please
 * see {@link \Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\FlatToStandard\EntityWithValuesDelocalized }
 *
 * @author    Aman Srivastava <aman.srivastava462@webkul.com>
 * @copyright 2019 Webkul Soft. Pvt. Ltd. (http://www.webkul.com)
 */
class Product extends \BaseFlatToStandardProduct implements \ArrayConverterInterface
{
    /**
     * @param array $item
     *
     * @return array
     */
    protected function convertItem(array $item): array
    {
        $convertedItem = [];
        $convertedValues = [];
        $convertBundleValue = [];

        foreach ($item as $column => $value) {
            if ($this->fieldConverter->supportsColumn($column)) {
                $convertedField = $this->fieldConverter->convert($column, $value);
                $convertedItem = $convertedField->appendTo($convertedItem);
            } elseif (in_array($column, $this->BundlefieldType)) {
                $convertBundleValue[$column] = $value;
            } else {
                $convertedValues[$column] = $value;
            }
        }
        
        if (!empty($convertBundleValue) && isset($convertBundleValue['bundle_values']) && !empty($convertBundleValue['bundle_values'])) {
            $convertedItem['bundleOptions'] = $this->convertBundleProduct($convertBundleValue);
        }

        // die;
        $convertedValues = $this->productValueConverter->convert($convertedValues);

        if (empty($convertedValues)) {
            throw new \LogicException('Cannot find any values. There should be at least one identifier attribute');
        }

        $convertedItem['values'] = $convertedValues;

        $identifierCode = $this->attributeRepository->getIdentifierCode();
        if (!isset($convertedItem['values'][$identifierCode])) {
            throw new \LogicException(sprintf('Unable to find the column "%s"', $identifierCode));
        }

        $convertedItem['identifier'] = $convertedItem['values'][$identifierCode][0]['data'];

        return $convertedItem;
    }
    /**
     * @param array $item
     *
     * @throws \StructureArrayConversionException
     */
    protected function validateOptionalFields(array $item): void
    {
        $optionalFields = array_merge(
            ['family', 'enabled', 'categories', 'groups', 'parent'],
            $this->attrColumnsResolver->resolveAttributeColumns(),
            $this->getOptionalAssociationFields(),
            $this->BundlefieldType
        );

        // index $optionalFields by keys to improve performances
        $optionalFields = array_combine($optionalFields, $optionalFields);
        $unknownFields = [];

        foreach (array_keys($item) as $field) {
            if (!isset($optionalFields[$field])) {
                $unknownFields[] = $field;
            }
        }

        $nonLocalizableOrScopableFields = $this->filterNonLocalizableOrScopableFields($unknownFields);
        $unknownFields = array_diff($unknownFields, $nonLocalizableOrScopableFields);

        $messages = [];
        if (0 < count($unknownFields)) {
            $messages[] = count($unknownFields) > 1 ?
                sprintf('The fields "%s" do not exist.', implode(', ', $unknownFields)) :
                sprintf('The field "%s" does not exist.', $unknownFields[0]);
        }
        foreach ($nonLocalizableOrScopableFields as $nonLocalizableOrScopableField) {
            $messages[] = sprintf(
                'The field "%s" needs an additional locale and/or a channel information; '.
                'in order to do that, please set the code as follow: '.
                '\'%s-[locale_code]-[channel_code]\'.',
                $nonLocalizableOrScopableField,
                $nonLocalizableOrScopableField
            );
        }

        if (count($messages) > 0) {
            throw new \StructureArrayConversionException(join(' ', $messages));
        }
    }

    /**
     * This method filters a list of fields (attribute codes) to return only the existing attributes
     * that are scopable or localizable.
     *
     * @param string[]  $attributeCodes
     * @return string[]
     */
    private function filterNonLocalizableOrScopableFields(array $attributeCodes): array
    {
        $result = [];
        if (count($attributeCodes) === 0) {
            return $result;
        }

        $attributes = $this->attributeRepository->findBy(['code' => $attributeCodes]);
        foreach ($attributeCodes as $attributeCode) {
            $found = false;
            foreach ($attributes as $attribute) {
                if ($attribute->getCode() === $attributeCode &&
                    ($attribute->isLocalizable() || $attribute->isScopable())
                ) {
                    $found = true;
                }
            }
            if ($found === true) {
                $result[] = $attributeCode;
            }
        }

        return $result;
    }

    /**
     * this method is used for Convert Bundle Product
     * @param $items
     * @return $convertedItem
     */
    protected function convertBundleProduct($items)
    {
        $convertedItem = [];
        $convertedItem['bundle_price_type'] = isset($items['bundle_price_type']) &&
                                                ($items['bundle_price_type'] == 'dynamic' || $items['bundle_price_type'] == true) ?
                                                true :
                                                false;
        $convertedItem['bundle_price_view'] = isset($items['bundle_price_view']) ? $items['bundle_price_view'] : null;
        $convertedItem['bundle_sku_type'] = isset($items['bundle_sku_type']) &&
                                                ($items['bundle_sku_type'] == 'dynamic' || $items['bundle_sku_type'] == true) ?
                                                true :
                                                false;
        $convertedItem['bundle_weight_type'] = isset($items['bundle_weight_type']) &&
                                                ($items['bundle_weight_type'] == 'dynamic' || $items['bundle_weight_type'] == true) ?
                                                true :
                                                false;
        $convertedItem['shipment_type'] = isset($items['bundle_shipment_type']) ? $items['bundle_shipment_type'] : null;
        
        if (isset($items['bundle_values']) && !empty($items['bundle_values'])) {
            $bundleValues = explode('|', $items['bundle_values']);

            $bundleValuesArray = [];
            foreach ($bundleValues as $bundleValue) {
                $convert_to_array = explode(',', $bundleValue);

                for ($i=0; $i < count($convert_to_array); $i++) {
                    $key_value = explode('=', $convert_to_array [$i]);
                    $end_array[$key_value [0]] = $key_value [1];
                }
                $bundleValuesArray[] =  $end_array;
            };

            $unique_types = array_unique(array_map(function ($elem) {
                return $elem['name'];
            }, $bundleValuesArray));
            
            foreach ($unique_types as $key => $type) {
                $key = 'table'.$key;
                foreach ($bundleValuesArray as $value) {
                    if ($value['name'] === $type) {
                        $convertedItem[$key]['title'] = $type;
                        $convertedItem[$key]['type'] = isset($value['type']) ? $value['type'] : null;
                        $convertedItem[$key]['required'] = isset($value['required']) && ($value['required'] == '1' || $value['required'] == true) ? true : false;
                        $convertedItem[$key]['products'][] = [
                            "qty" => isset($value['default_qty']) ? (int)$value['default_qty'] : null,
                            "sku" => isset($value['sku']) ? $value['sku'] : null,
                            "is_default" => isset($value['default']) && ($value['default'] == '1' || $value['default'] == true) ? true : false,
                            "can_change_quantity" => isset($value['can_change_quantity']) && ($value['can_change_quantity'] == '1' || $value['can_change_quantity'] == true) ? true : false,
                        ];
                    }
                }
            }
        }

        return $convertedItem;
    }

    /**
     * Bundle field types
     */
    protected $BundlefieldType = [
                            "bundle_price_type",
                            "bundle_price_view",
                            "bundle_shipment_type",
                            "bundle_sku_type",
                            "bundle_values",
                            "bundle_weight_type",
                            "product_type"
                        ];
}
