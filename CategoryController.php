<?php

namespace App\Http\Controllers;

use App\Attribute;
use App\Helpers\Frontend\SessionHelper;
use App\Http\Resources\CategoryResource;
use App\Manufacturer;
use App\Models\Category\Category;
use App\CategoryLang;
use App\Models\Category\TransCategory;
use App\Product;
use App\Store;
use App\XmlCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\Builder;
use App\CategorySort;

class CategoryController extends Controller
{
    private static function createSlug(Request $request)
    {
        $data = $request->only(["title","titleHelp"]);
        return self::slugify($data["title"]);

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return CategoryResource::collection(Category::orderBy('sort_id', 'asc')->get());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required',
            'meta_title' => 'required',
            'meta_description' => 'required',
                'keywords' => 'required',
        ]);
        $data = $request->all();
        $multiStoreID = Store::where('main',1)->first()->id;
        if(isset($_GET['multistore'])) {
            $_GET['multistore'] = $multiStoreID;
        }
        $idParent = null;
        $category = new Category(array_merge($request->only([
            "description",
            "meta_title",
            "meta_description",
            "keywords",
            "titleHelp",
            "isRoot",
            "discount"
        ]), [
            "parent_id" => $idParent,
            "name"=>$request->get("title"),
            "slug"=>self::createSlug($request)
        ],$this->createExportArrays($request)));
        $category->save();

        $stores = Store::all();
        foreach($stores as $store) {
            $this->addEditTransCategory(false, $category, $store->id, $category, $request);
        }

        $image = $request->get("image");
        if ($image != null) {
            $image = $image["id"];
            if ($image > 0) {
                $category->fill(["image_id" => $image]);
            }
        }
        $sub = $request->get("subCategories");
        $category->save();
        foreach($data['xmlCategories'] as $selectedXml) {
            $xmlCategory = new XmlCategory();
            $xmlCategory->category_id = $category->id;
            $xmlCategory->xml_name = $selectedXml['xml_name'];
            $xmlCategory->xml_category_id = $selectedXml['xml_category_id'];
            $xmlCategory->xml_category_name = $selectedXml['xml_category_name'];
            $xmlCategory->save();
        }
        collect($sub)->each(function ($one, $key) use ($category){
            $cat = Category::find($one["id"]);
            $cat->parent_id = $category->id;
            $cat->save();
            $subIds = [];
            $subIds[$cat->id] = ['sort' => $key];
            $category->subcategory_sort()->syncWithoutDetaching($subIds);
        });


        return new CategoryResource($category);
    }

    private function addEditTransCategory($isUpdated = false, $data, $multiStoreID, $category, $request) {
        if(!$isUpdated) {
            $transCategory = new TransCategory();
        }else {
            $transCategory = TransCategory::where('store_id',$multiStoreID)->where('category_id',$category->id)->first();
        }
        $transCategory->category_id = $category->id;
        $transCategory->store_id = $multiStoreID;
        $transCategory->slug = self::createSlug($request);
        $transCategory->name = $data['name'];
        $transCategory->description = $data['description'];
        $transCategory->meta_title = $data['meta_title'];
        $transCategory->meta_description = $data['meta_description'];
        $transCategory->keywords = $data['keywords'];
        $transCategory->save();
    }

    public static function slugify($text)
    {
        // replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate -
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    /**
     * Display the specified resource.
     *
     * @param Manufacturer $manufacturer
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $category = Category::find($id);
        $categoryResource = new CategoryResource($category);
        return $categoryResource;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Manufacturer $manufacturer
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $data = $request->all();
        $category = Category::find($id);
        if (empty($category)) abort(404);
        $data = $request->all();
        $mainMultiStoreId =  Store::where('main',1)->first()->id;
        $multiStoreId =  $mainMultiStoreId;
        if(isset($_GET['multistore'])) {
            $multiStoreId = $_GET['multistore'];
            $_GET['multistore'] = $mainMultiStoreId;
        }
        $updateTransRow = false;
        if(isset($data['multistore'])) {
            if(Store::where('main',1)->where('id',$data['multistore'])->first()) {
                $category = $this->addEditCategory(true, $category, $request);
            }
            $updateTransRow = true;
        }else{
            $category = $this->addEditCategory(true, $category, $request);
        }

        if($updateTransRow) {
            $this->addEditTransCategory(true, $data, $multiStoreId, $category, $request);
        }



        XmlCategory::where('category_id',$category->id)->delete();
        foreach($data['xmlCategories'] as $selectedXml) {
            $xmlCategory = new XmlCategory();
            $xmlCategory->category_id = $category->id;
            $xmlCategory->xml_name = $selectedXml['xml_name'];
            $xmlCategory->xml_category_id = $selectedXml['xml_category_id'];
            $xmlCategory->xml_category_name = $selectedXml['xml_category_name'];
            $xmlCategory->save();
        }

        $sub = $request->get("subCategories");
        $category->children()->get()->each(function ($one)use ($id, $category){
            $one->parent_id = null;
            $one->save();
            $category->subcategory_sort()->detach([$one->id]);
        });

        collect($sub)->each(function ($one, $key) use ($id, $category){
            $cat = Category::find($one["id"]);
            $cat->parent_id = $id;
            $cat->save();
            $subIds = [];
            $subIds[$cat->id] = ['sort' => $key];
            $category->subcategory_sort()->syncWithoutDetaching($subIds);
        });


        return new CategoryResource($category);
    }

    public function addEditCategory($is_updated = true,$category, $request) {
        $category->fill(array_merge($request->only([
            "description",
            "slug",
            "meta_title",
            "meta_description",
            "keywords",
            "titleHelp",
            "isRoot",
            "discount"
        ]),
            ["name"=>$request->get("title"),"slug"=>self::createSlug($request)],
            $this->createExportArrays($request)
        ));

        if($request->get("isRoot",false)){
            $category->parent_id = null;
        }

        $image = $request->get("image");
        if ($image != null) {
            $image = $image["id"];
            $category->fill(["image_id" => $image]);
        } else {
            $category->fill(["image_id" => null]);
        }
        $category->save();
        return $category;
    }

    /**
     * @param $id
     * @param $status
     * @return \Illuminate\Http\JsonResponse
     */

    public function updateStatus($id, $status)
    {
        if (((int) $status !== 0 && (int) $status !== 1) || !$id) {
            return response()->json([
                'error' => 'Category List not found'
            ],404);
        }

        $product = Category::where('id', $id);

        $product->update([
            'is_active' => $status
        ]);

        return response()->json([
            'message'=> 'Status successfully updated'
        ], 200);
    }

    //

    public function changeLayout(Request $request)
    {
        $idArr = $request->all();
        if (!is_array($idArr)) {
            return response()->json([
                'error' => 'List of id is not array'
            ],404);
        }

       foreach ($idArr as $item) {
          $id = $item['id'];
           $category = Category::where('id', $id);
           $category->update([
               'sort_id' => $item['sort_id']
           ]);
       }

       return response()->json([
           'message'=> 'Sort list successfully updated'
       ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Manufacturer $manufacturer
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

        $cat = Category::find($id);
        if (empty($cat)) abort(404);
        $cat->delete();

        return response()->noContent();
    }

    public function listAll(Request $request)
    {
        $categories = Category::all();
        return CategoryResource::collection($categories);
    }

    public function list()
    {
        return CategoryResource::collection(Category::all());
    }

    /**
     * Validation rules
     *
     * @return array
     */
    protected function rules(): array
    {
        //TODO CREATE VALIDATION  RULES
        return [
            'name' => 'required|string|max:1024|unique:manufacturers,name'
        ];
    }


    /**
     * @param Request $request
     * @param null    $categorySlug
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showPage(Request $request, $categorySlug=null)
    {

        $category = Category::with('products')->where(["slug"=> $categorySlug ])->first();

        if(empty($category))abort(404);
        $product_ids = $category->products()->get()->pluck('id')->all();
        $manufacturers = Manufacturer::with('image')->whereHas('products', function ($query) use ($product_ids){
            $query->whereIn('id', $product_ids);
        })->get();

        return view('store.category.index', [
            "category"=>$category,
            "manufacturers"=>$manufacturers,
        ]);

    }

    /**
     * @param $products
     * @return array
     */
    public function filtersValue($products){
        $products_filter = $products->get()->toArray();
        $values = [];

        foreach ($products_filter as $product){
            foreach($product['variants'] as $val){
                foreach($val['values'] as $val_data){
                    $values[] = ['attribute' => $val_data['attribute']['name'], 'value_id' => $val_data['id'], 'value' => $val_data['value']];
                }
            }
        }

        $res = [];
        $check = false;
        foreach($values as $key => $val){
            if(!isset($res[$val['attribute']])){
                $res[$val['attribute']][] = ['attribute' => $val['attribute'], 'value_id' => $val['value_id'], 'value' => $val['value']];
            } else {
                foreach ($res[$val['attribute']] as $attr){
                    if(!empty($attr['value_id']) && $attr['value_id'] == $val['value_id']){
                        $check = true;
                    }
                }
                if($check == false){
                    $res[$val['attribute']][] = $val;
                }
            }
        }

        return $res;
    }

    private function createExportArrays(Request $request)
    {
       $maps = $request->get("exportMaps",null);
       $data = collect();
       if(!empty($maps)){
           $maps = collect($maps);
           $glami = $maps->get("glami",false);
           if($glami){
               $data->put("glami_id",$glami["id"]);
           }
           $glami = $maps->get("heureka",false);
           if($glami){
               $data->put("heureka_id",$glami["id"]);
           }
       }
       return $data->toArray();
    }

    public function changeSubCategorySort(Request $request)
    {
        $data = $request->all();
        foreach ($data['idList'] as $item) {
            CategorySort::updateOrCreate(
                [ "category_id" => $data['category_id'] , "subcategory_id" => $item["subcategory_id"]],
                [ "name" => $data['category_id'], "subcategory_id" => $item["subcategory_id"], "sort" => $item['sort']]
            );
        }
    }
}
