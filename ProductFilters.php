<?php

namespace App\Filters;

use App\Models\Category\Category;
use App\Filters\Filters;
use App\Supplier;
use App\User;
use Illuminate\Database\Eloquent\Builder;

class ProductFilters extends Filters
{
    protected $filters = [
        'id',
        'name',
        'code',
        'priceMin',
        'priceMax',
        'retailPriceMin',
        'retailPriceMax',
        'status',
        'quantity',
        'categories',
        'weightFilter',
        'supplierFilter'
    ];

    public function id($id)
    {
        return $this->builder->where('id', $id);
    }

    public function name(string $search)
    {
        return $this->builder->where('name', 'like', "%$search%");
    }

    public function code($code)
    {
        return $this->builder->where('code', $code);
    }

    public function priceMin($priceMin)
    {
        return $this->builder->where('retail_price_with_iva', '>=', $priceMin);
    }

    public function priceMax($priceMax)
    {
        return $this->builder->where('retail_price_with_iva', '<=', $priceMax);
    }

    public function weightFilter($weightFilter)
    {
        return $this->builder->where('weight', $weightFilter);
    }

    public function retailPriceMin($retailPriceMin)
    {
        return $this->builder->where('retail_price', '>=', $retailPriceMin);
    }

    public function retailPriceMax($retailPriceMax)
    {
        return $this->builder->where('retail_price', '<=', $retailPriceMax);
    }

    public function supplierFilter($supplier)
    {
        $supplier_id = Supplier::where('name', $supplier)->first()->id;
        return $this->builder->whereHas('suppliers', function (Builder $query) use ($supplier_id) {
            $query->where('supplier_id', $supplier_id);
        });
    }


    public function status($status)
    {
        return $this->builder->where('status', $status);
    }

    public function quantity($quantity)
    {
        return $this->builder->where('quantity', $quantity);
    }

    public function categories($categories)
    {
        $ids = explode(',', $categories);

        return $this->builder->whereHas('categories', function (Builder $query) use ($ids) {
            $query->whereIn('category_id', $ids);
        });
    }

}
