<?php

namespace App\Repositories;

use App\File;
use App\Filters\ProductFilters;
use App\Http\Requests\Api\ProductStoreRequest;
use App\Http\Requests\Api\ProductUpdateRequest;
use App\Models\Product\TransProduct;
use App\Product;
use App\ProductDetails;
use App\Store;
use App\Variant;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;

class ProductRepository extends AbstractRepository
{
    public function getModelClass()
    {
        return Product::class;
    }

    public function filtered($paginate, ProductFilters $filters, Request $request)
    {
        $data = $request->all();
        return $this->model
            ->whereHas('translations',function($query) use ($data){
                if(isset($data['multistore'])){
                    $query->where('store_id',$data['multistore']);
                }
            })
            ->filter($filters)
            ->paginate($paginate);
    }

    public function createFromRequest(ProductStoreRequest $request)
    {
        $multiStoreID = Store::where('main',1)->first()->id;
        if(isset($_GET['multistore'])) {
            $_GET['multistore'] = $multiStoreID;
        }
        $product = Product::create($request->productData());
        $stores = Store::all();
        foreach($stores as $store) {
            $this->addEditTransProduct(false, $request->productData(), $store->id, $product);
        }

        if(isset($_GET['multistore'])) {
            $_GET['multistore'] = Store::where('main',1)->first()->id;
            $product = Product::find($product->id);
        }

        // translate
        foreach ($request->stores() as $store) {

            $product->setTranslatedFields($store['locale'], $store['fields']);
        }

        // create variants first, so we can assign files later
        if (empty($request->variants)) {
            $this->createVariant($product, $request->metaVariantData());
        } else {
            foreach ($request->variants() as $variant) {
                $this->createVariant($product, $variant);
            }
        }

        if (!empty($request->product_details)) {
            $this->createProductDetails($product, $request);
        }

        if ($request->image()) {
            $product->image()->associate($request->image());
            $product->save();
        }

        if (! empty($request->images)) {
            $product->images()
                ->attach(Arr::pluck($request->images, 'id'));
        }

        if (! empty($request->categories)) {
            $product->categories()
                ->attach(Arr::pluck($request->categories, 'id'));
        }

        if (! empty($request->product_statuses)) {
            $product->product_statuses()->attach(Arr::pluck($request->product_statuses, 'id'));
        }

        if (! empty($request->manufacturers)) {
            $product->manufacturers()
                ->attach(Arr::pluck($request->manufacturers, 'id'));
        }

        if (! empty($request->product_models)) {
            $product->product_models()
                ->attach(Arr::pluck($request->product_models, 'id'));
        }

        if (! empty($request->suppliers)) {
            $product->suppliers()
                ->attach(Arr::pluck($request->suppliers, 'id'));
        }

        return $product;
    }

    private function addEditTransProduct($is_updated = false, $request, $multistoreID, $product) {
        if($is_updated) {
            $transProduct = TransProduct::where('store_id',$multistoreID)->where('product_id',$product->id)->first();
        }else{
            $transProduct = new TransProduct();
        }
        $transProduct->slug = $product->slugName($product['name']);
        $transProduct->name = $request['name'];
        $transProduct->short_description = $request['short_description'];
        $transProduct->description = $request['description'];
        $transProduct->store_id = $multistoreID;
        $transProduct->product_id = $product->id;
        $transProduct->retail_price = $request['retail_price'];
        $transProduct->wholesale_price = $request['wholesale_price'];
        $transProduct->retail_price_with_iva = $request['retail_price_with_iva'];
        $transProduct->wholesale_price_with_iva = $request['wholesale_price_with_iva'];
        $transProduct->save();
    }

    public function updateFromRequest(ProductUpdateRequest $request, Product $product)
    {
        $data = $request->all();
        $updateTransRow = false;
        if(isset($data['multistore'])) {
            if(Store::where('main',1)->where('id',$data['multistore'])->first()) {
                $product->update($request->productData());
            }
            $updateTransRow = true;
        }else{
            $product->update($request->productData());
        }

        $mainMultiStoreId =  Store::where('main',1)->first()->id;
        $multiStoreId =  $mainMultiStoreId;
        if(isset($_GET['multistore'])) {
            $multiStoreId = $_GET['multistore'];
            $_GET['multistore'] = $mainMultiStoreId;
            $product = Product::find($product->id);
        }
        if($updateTransRow) {
            $this->addEditTransProduct(true, $request->productData(), $multiStoreId, $product);
        }
        $translation = [];
        foreach ($request->stores() as $store) {
            $translation[$store['locale']] = $store['fields'];
        }

        //TODO FIX
        //$product->overwriteTranslations($translation);

        $this->updateProductVariants($request, $product);
        $this->createProductDetails($product, $request);

        //zratam množstvo na variantach a upravím
        $product->quantity = $product->variantsQuanity($product);
        $product->image()
            ->associate($request->image());
        $product->images()
            ->sync($request->images());
        $product->categories()
            ->sync($request->categories());
        $product->manufacturers()
            ->sync($request->manufacturers());
        $product->product_models()
            ->sync($request->product_models());
        $product->suppliers()
            ->sync($request->suppliers());
        $product->product_statuses()
            ->sync($request->product_statuses());


        $product->save();

        return $product;
    }

    private function updateProductVariants(ProductUpdateRequest $request, Product $product)
    {
        $keepIds = Arr::pluck($request->variants, 'id');

        // Remove all values not present in the request
        $product->variants()
            ->whereNotIn('id', $keepIds)
            ->delete();

        foreach ($request->variants as $variant) {
            if (! array_key_exists('id', $variant)) {
                throw new \Exception('Variant ID has not been provided.');
            }
            // If ID != 0, means we are updating existing Value
            if ($variant['id'] > 0) {
                $storedVariant = Variant::findOrFail($variant['id']);
                $this->updateVariant($storedVariant, $variant);

                continue;
            }
            $this->createVariant($product, $variant);
        }
    }

    private function createProductDetails(Product $product, $request)
    {
        foreach($request->product_details as $product_detail) {
            ProductDetails::updateOrCreate(
                ["field" => $product_detail["field"], "name" => $product_detail["name"], "product_id" => $product->id],
                ["value" => $product_detail['value']]
            );
        }
    }


    private function createVariant(Product $product, array $variantData)
    {
        $persistedVariant = $product->variants()
            ->create(Arr::except($variantData, ['attributes']));

        $persistedVariant->values()
            ->attach(Arr::pluck($variantData['attributes'], 'value.id'));
    }

    private function updateVariant(Variant $variant, array $variantData)
    {
        $variant->update(Arr::except($variantData, ['attributes', 'id']));

        $variant->values()
            ->sync(Arr::pluck($variantData['attributes'], 'value.id'));
    }

}
