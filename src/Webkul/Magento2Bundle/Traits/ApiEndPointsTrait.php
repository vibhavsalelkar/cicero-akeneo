<?php

namespace Webkul\Magento2Bundle\Traits;

/**
* trait used to getOAuthClient Api EndPoints
*/
trait ApiEndPointsTrait
{
    private $apiEndpoints = [
            'storeViews'          => '/rest/V1/store/storeViews',
            'storeConfigs'        => '/rest/V1/store/storeConfigs',
            'product'             => '/rest/{_store}/V1/products/{sku}?searchCriteria=',
            'addProductQueue'     => '/rest/{_store}/async/V1/add-products',
            'currency'            => '/rest/{_store}/V1/directory/currency',
            'getAttributeSets'    => '/rest/{_store}/V1/products/attribute-sets/sets/list?searchCriteria[pageSize]=50',
            'addAttributeSet'     => '/rest/{_store}/V1/products/attribute-sets',
            'updateAttributeSet'  => '/rest/{_store}/V1/products/attribute-sets/{attributeSetId}',
            'addToAttributeSet'   => '/rest/{_store}/V1/products/attribute-sets/attributes',
            'getAttributeSet'     => '/rest/{_store}/V1/products/attribute-sets/{attributeSetId}/attributes',
            'configurableOptions' => '/rest/{_store}/V1/configurable-products/{sku}/options/all',
            'addChild'            => '/rest/{_store}/V1/configurable-products/{sku}/child',
            'categories'          => '/rest/{_store}/V1/categories?searchCriteria=',
            'updateCategory'      => '/rest/{_store}/V1/categories/{category}',
            'attributes'          => '/rest/{_store}/V1/products/attributes?searchCriteria=',
            'getAttributes'       => '/rest/{_store}/V1/products/attributes/{attributeCode}',
            'updateAttributes'    => '/rest/{_store}/V1/products/attributes/{attributeCode}',
            'deleteAttributes'    => '/rest/{_store}/V1/products/attributes/{attributeCode}',
            'attributeOption'     => '/rest/{_store}/V1/products/attributes/{attributeCode}/options',
            'deleteAttributeOption'=> '/rest/{_store}/V1/products/attributes/{attributeCode}/options/{option}',
            'addAttributeGroup'   => '/rest/{_store}/V1/products/attribute-sets/groups',
            'getAttributeGroup'   => '/rest/{_store}/V1/products/attribute-sets/groups/list?searchCriteria=',
            'addCategoryToProduct' => '/rest/{_store}/V1/categories/{categoryId}/products',
            'getProduct'          => '/rest/{_store}/V1/products/{sku}',
            'addLinks'            => '/rest/{_store}/V1/products/{sku}/links',
            'getLinks'            => '/rest/{_store}/V1/products/{sku}/links/{type}',
            'deleteLinks'         => '/rest/{_store}/V1/products/{sku}/links/{type}/{linkedProductSku}',
            'custom_fields'       => '/rest/V1/custom-products-render-info?searchCriteria=[]&store_id={store}&currency_code={currency}',
            'getProductMedias'    => '/rest/V1/products/{sku}/media',
            'removeProductMedia'  => '/rest/V1/products/{sku}/media/{entryId}',
            'updateProductMedia'  => '/rest/{_store}/V1/products/{sku}/media/{entryId}',
            'addProductMedia'     => '/rest/{_store}/V1/products/{sku}/media/{entryId}',
            'removeCategoryProduct' => '/rest/V1/categories/{categoryId}/products/{sku}',
            'getWebsites'         => '/rest/V1/store/websites',
            'customerGroups'      => '/rest/V1/customerGroups/search?searchCriteria',
            'getDownloadableProductsLinks' => '/rest/V1/products/{sku}/downloadable-links',
            'postDownloadableProductsLinks' => '/rest/V1/products/{sku}/downloadable-links',

            /* support for free product attachment https://github.com/mageprince/magento2-productAttachment */
            'productAttachment'   => '/rest/V1/productattach/addupdate',
            'getProductAttachment' => '/rest/V1/productattach/{id}',
            'deleteProductAttachment' => '/rest/V1/productattach/delete/{id}',
            'amastyProductAttachment' => '/rest/V1/productAttachment',
            'updateDeleteAmastyProductAttachment' => '/rest/V1/productAttachment/{id}',
            /* support for https://amasty.com/product-parts-finder-for-magento-2.html */
            'amastyProductParts'  => '/rest/V1/amasty_finder/saveProduct',
            'amastyPartsDropdown'  => '/rest/V1/amasty_finder/dropdown/all',
            'addAmastyPartsDropdown'  => '/rest/V1/amasty_finder/dropdown',

            'getSeller'           => '/rest/V1/mpapi/admin/sellers/{id}',
            'getSellers'          => '/rest/V1/mpapi/admin/sellers?searchCriteria[pageSize]=',
            'createSeller'        => '/rest/V1/mpapi/sellers/create',
            'assignProductsToSeller' => '/rest/V1/mpapi/admin/sellers/{sellerId}/assign',
            'getCustomers'        => '/rest/V1/customers/search',
            'getCategory'        => '/rest/{_store}/V1/categories/{id}',
            'deleteCategory'    => '/rest/{_store}/V1/categories/{id}',
            
            /* support for bundle discount bundle */
            'addBundleDiscountPoducts'      => '/rest/V1/bundleproducts',
            'deleteBundleDiscountPoducts' =>'/rest/V1/bundleproductsdelete'         
        ];
}
