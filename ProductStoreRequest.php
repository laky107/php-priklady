<?php

namespace App\Http\Requests\Api;

use App\File;
use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class ProductStoreRequest extends AbstractRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string',
            'short_description' => 'required',
            'description' => 'required|min:5',
            'image' => 'array',
                'image.id' => 'numeric|exists:files,id',
            'images' => 'array',
                'images.*.id' => 'nullable|numeric|exists:files,id',
            // 'id_language' =>
            'code' => 'nullable|string|unique:products',
            'status' => [
                'required',
                Rule::in(['available', 'ended', 'arrival']),
            ],
            'wholesale_price' => 'numeric',
            'retail_price' => 'numeric',
            'wholesale_price_with_iva' => 'numeric',
            'retail_price_with_iva' => 'numeric',
            'quantity' => 'numeric',
            'discount' => 'numeric',
            'variants' => 'required|array',
                'variants.*.quantity' => 'numeric',
                'variants.*.code' => 'nullable|string|unique:variants',
                'variants.*.retail_price' => 'numeric',
                'variants.*.wholesale_price' => 'numeric',
                'variants.*.retail_price_with_iva' => 'numeric',
                'variants.*.wholesale_price_with_iva' => 'numeric',
                    'variants.*.attributes.*.id' => 'numeric|exists:attributes,id',
                        'variants.*.attributes.*.value.id' => 'numeric|exists:values,id',
            'manufacturers' => 'array',
                'manufacturers.*.id' => 'numeric|exists:manufacturers,id',
            'suppliers' => 'required|array',
                'suppliers.*.id' => 'numeric|exists:suppliers,id',
            'categories' => 'required|array',
                'categories.*.id' => 'numeric|exists:categories,id',
            'stores' => 'array',
                'stores.*.id' => 'numeric|exists:stores,id',
                'stores.*.fields' => 'array',
                    'stores.*.fields.name' => 'required|string',
                    'stores.*.fields.short_description' => 'required|min:5',
                    'stores.*.fields.description' => 'required',
        ];
    }

    public function productData(): array
    {
        return $this->only([
            'name',
            'short_description',
            // 'enabled',
            'description',
            'code',
            'status',
            'wholesale_price',
            'retail_price',
            'wholesale_price_with_iva',
            'retail_price_with_iva',
            'quantity',
            'discount',
            'weight'
        ]);
    }

    public function metaVariantData()
    {
        return array_merge($this->only([
                'code',
                'quantity',
                'wholesale_price',
                'retail_price',
                'wholesale_price_with_iva',
                'retail_price_with_iva',
            ]),
            ['attributes' => []]
        );
    }

    public function variants()
    {
        return $this->get('variants');
    }

    public function image(): ?File
    {
        return File::findOrFail($this->get('image')['id']);
    }

    public function images(): array
    {
        return $this->get('images');
    }

    public function stores(): array
    {
        return $this->get('stores') ?: [];
    }
}
