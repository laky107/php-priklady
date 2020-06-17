<?php

namespace App\Models\Category;

use App\CategorySort;
use App\File;
use App\Helpers\Frontend\SessionHelper;
use App\Manufacturer;
use App\Product;
use App\Store;
use App\Supplier;
use App\Traits\Localizable;
use App\XmlCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class Category extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public static function getAllFiltered()
    {

        if(Session::exists("globalFilter")){
            return Category::childrenAll(Session::get("globalFilter"));
        }
        else{
            return self::all();
        }
    }

    protected static function boot()
    {
        parent::boot();
        self::retrieved(function ($model) {
            if (app()->getLocale() != config('app.fallback_locale')) {
                $model->localize();
            }elseif (isset($_GET['multistore'])) {
                if(!Store::where('main',1)->where('id',$_GET['multistore'])->count()) {
                    $multistore = Store::find($_GET['multistore']);
                    if(app()->getLocale() != $multistore->locale) {
                        $model->localize($multistore->id, $multistore->currency);
                    }
                }
            }
        });

        self::deleting(function ($model) {
            $model->translations()->delete();
        });
    }

    private static function childrenAll(int $id)
    {
        return Category::where(["parent_id"=> $id])->get();
    }

    public function localize($localeID = null, $currency = null)
    {
        $trans = $this->translations()
            ->where('store_id', $localeID ? $localeID : app('activeStore')->id)
            ->firstOrFail();
        $this->slug = $trans->slug;
        $this->name = $trans->name;
        $this->description = $trans->description;
    }

    public function translations()
    {
        return $this->hasMany(TransCategory::class);
    }

    public function subcategory_sort()
    {
        return $this->belongsToMany(Category::class, 'category_sort','category_id', 'subcategory_id')->orderBy('sort', 'asc');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class);
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id', 'id');
    }

    public function image()
    {
        return $this->belongsTo(File::class, 'image_id');
    }

    public function bestSellers()
    {
        return $this->products()
            ->latest()
            ->take(4)
            ->get();
    }

    /**
     * Generate CATEGORYTEXT for Heureka by their spec
     *
     * @return string CATEGORYTEXT
     */
    public function heurekaCategoryText(): string
    {
        $category = $this->category;

        $titles = [];
        while ($category) {
            $titles[] = $category->title;
            $category = $category->parent;
        }

        return implode(' | ', $titles);
    }

    public static function categoryList()
    {
        return self::allTreeList();
    }

    public static function allTreeList()
    {
        return DB::table('categories')
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->get();
    }

    public function chidrenToList($removeStop)
    {
        $list = collect();
        foreach ($this->children()->get() as $category) {
            $obj = Category::find($category->id);
            if (!empty($removeStop)) {
                if ($removeStop != $obj->id) {
                    $list->push(["id" => $obj->id, "name" => $obj->title, "children" => $obj->chidrenToList($removeStop)]);
                }
            } else {
                $list->push(["id" => $obj->id, "name" => $obj->title, "children" => $obj->chidrenToList($removeStop)]);
            }

        }
        return $list->toArray();
    }

    public  function route(){
        $route = collect();
        $route->push($this);
        $break = false;
        $one = $this;
        while (!$break){
            if($one->parent()->get()->count()>=1){
                $one = $one->parent()->get()->first();
                $route->push($one);
            }else{
                $break = true;
            }
        }
        return $route->reverse();
    }

    public function childrenArray(){
        return $this->subcategory_sort->toArray();
    }

    public static function topCategory(){

        $cathelperName = SessionHelper::getGlobalCategory(true);
        $count = Category::where(["titleHelp"=>$cathelperName])->count();
        if($count>4){
            return  Category::where(["titleHelp"=>$cathelperName])->get()
                ->random(4)->toBase();
        }else{
            return  Category::where(["titleHelp"=>$cathelperName])->get()->toBase();
        }

    }

    public function suppliersList(){
        $suppliersId = collect();
        $this->products()->each(function (Product $product) use ($suppliersId){

            $suppliersId->push($product->suppliers()->get()->pluck("id")->toArray());
        });
        $ids = collect();
        $suppliersId->each(function ($data)use($ids){
            collect($data)->each(function ($one)use($ids){
                $ids->push($one);
            });
        });
        return Supplier::find($ids->unique()->toArray());
    }

    public function manufacturersList(){
        $manufacturersid = collect();
        $this->products()->each(function (Product $product) use ($manufacturersid){

            $manufacturersid->push($product->manufacturers()->get()->pluck("id")->toArray());
        });
        $ids = collect();
        $manufacturersid->each(function ($data)use($ids){
            collect($data)->each(function ($one)use($ids){
                $ids->push($one);
            });
        });
        return Manufacturer::find($ids->unique()->toArray());
    }

    public function parentArray(){
        return $this->parent()->get()->toArray();
    }

    public static function recomendedCategory(){
        //TODO FIX ME
        return  Category::whereIn("id",[40,90,42,88])->get()->toBase();
    }


    public function xmlCategories()
    {
        return $this->hasMany(XmlCategory::class, "category_id", "id");
    }



}
