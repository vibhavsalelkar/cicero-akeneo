<?php

namespace Webkul\Magento2BundleProductBundle\Validator\Constraints;

use Akeneo\Tool\Component\StorageUtils\Exception\UnknownPropertyException;

/**
 * @Annotation
 */
class BundleOptionValidator
{
    public function validateBundleOptions($data, $connectorService)
    {
        $voilations = [];

        foreach ($data as $dataKey =>$field) {
            if (is_array($field)) {
                foreach ($field as $fieldKey =>$fieldValue) {
                    if (is_array($fieldValue)) {
                        foreach ($fieldValue as $fieldValueKey =>$innerFieldValue) {
                            foreach ($innerFieldValue as $innerFieldValueKey =>$innerValue) {
				    switch ($innerFieldValueKey) {
				    case 'option_id':	    
				    case 'qty':
				    case 'id':	    
				    case 'position':		    
				    case 'price':	   
				    case 'price_type':	    
					    $innerValue = intval($innerValue);


                                        if (!filter_var($innerValue, FILTER_VALIDATE_INT) && $innerValue !== 0) {
                                            $voilations['voilation'][$dataKey][$fieldKey][$fieldValueKey][$innerFieldValueKey]="This field value must be of integer type";
                                        } else{
                                            if ($innerValue < 1 && in_array($innerFieldValueKey, ['qty'])) {
                                                $voilations['voilation'][$dataKey][$fieldKey][$fieldValueKey][$innerFieldValueKey]="This field value must be of 1 or greater";
                                            }
                                        }
                                        break;
                                    case 'sku':
                                        $product = $connectorService->findProductByIdentifier($innerValue);
                                        if (!$product) {
                                            $voilations['voilation'][$dataKey][$fieldKey][$fieldValueKey][$innerFieldValueKey]= "No product found with sku ".$innerValue;
                                        }
                                        break;
                                    case 'is_default':
        
                                        if (gettype($innerValue) !== 'boolean') {
                                            $voilations['voilation'][$dataKey][$fieldKey][$fieldValueKey][$innerFieldValueKey]= "This field value must be of boolean type (true / false)";
                                        }
                                       break;
				    case 'can_change_quantity':
                                      if($innerValue==0 || $innerValue==1){
                                         $boolval=true;
                                      }else{
                                          $boolval=false;
                                      }
                                     
                                        if (!filter_var($boolval, FILTER_VALIDATE_BOOLEAN)) {
                                            $voilations['voilation'][$dataKey][$fieldKey][$fieldValueKey][$innerFieldValueKey]= "This field value must be of boolean type (true / false)";
                                        }
                                        break;
				    default:
					throw UnknownPropertyException::unknownProperty($innerFieldValueKey);
                                }
                            }
                        }
                    } else {
                        switch ($fieldKey) {
                            case 'type':
                                if (!in_array($fieldValue, ['select', 'radio', 'checkbox','multi'], true)) {
                                    $voilations['voilation'][$dataKey][]= [
                                        'type' => "This field value must be in (select, radio, checkbox, multi)"
                                    ];
                                }
                                break;
                            case 'title':
                                if (gettype($fieldValue) !== 'string') {
                                    $voilations['voilation'][$dataKey][]= [
                                        'title' => "This field value must be of string type"
                                    ];
                                }
                                break;
                            case 'required':
                                if (gettype($fieldValue) !== 'boolean') {
                                    $voilations['voilation'][$dataKey][]= [
                                        'required' => "This field value must be of boolean type (true / false)"
                                    ];
                                }
				break;
			    case 'position':	
			    case 'option_id':
			    case 'sku':	   
		            case 'id':		        
		                break;		    
			    default:
                                throw UnknownPropertyException::unknownProperty($fieldKey);
                        }
                    }
                }
            } else {
                switch ($dataKey) {
                    case 'bundle_price_type':
                        if (gettype($field) !== 'boolean') {
                            $voilations['voilation'][]= [
                                'bundle_price_type' => "This field must be of boolean (true / false)"
                            ];
                        }
                        break;
                    case 'bundle_price_view':
                        if (!in_array($field, ['Price range', 'As low as'], true)) {
                            $voilations['voilation'][]= [
                                'bundle_price_view' => "This value must be either Price Range or As low as"
                            ];
                        }
                        break;
                    case 'bundle_sku_type':
                        if (gettype($field) !== 'boolean') {
                            $voilations['voilation'][]= [
                                'bundle_sku_type' => "This field must be of boolean type (true / false)"
                            ];
                        }
                        break;
                    case 'bundle_weight_type':
                        if (gettype($field) !== 'boolean') {
                            $voilations['voilation'][]= [
                                'bundle_weight_type' => "This field must be of boolean type (true / false)"
                            ];
                        }
                        break;
                    case 'shipment_type':
                        if (!in_array($field, ['separately', 'together'], true)) {
                            $voilations['voilation'][]= [
                                'bundle_price_view' => "This value must be either together or separately"
                            ];
                        }
                        break;
		    default:
                        throw UnknownPropertyException::unknownProperty($dataKey);
                }
            }
        }

        return $voilations;
    }
}
