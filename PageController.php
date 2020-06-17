<?php

namespace App\Http\Controllers;

use App\ContentPage;
use App\DynamicField;
use App\Http\Resources\PageResource;
use App\Http\Resources\StoreResource;
use App\Page;
use App\Store;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        return PageResource::collection(Page::paginate(15));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $page = new Page($request->only([
            'title',
            'content',
            'slug',
            'list'
        ]));
        $id = $request->get("list");
        $name = null;
        foreach ($request->get("listPage") as $one) {
            if ($one["key"] == $id) {
                $name = $one["label"];
            }
        }
        $page->template_file = 'pages.' . $name;
        $page->save();

        (new SeoController())->createRecord($page,"PAGE");
        return response()->json(['data' => $page]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Page $page)
    {
        $dir = base_path() . '/resources/views/pages';
        $files = scandir($dir, 1);
        $stack = [];
        foreach ($files as $key => $value) {
            if ($value != '..' && $value != '.')
                array_push($stack, ["label" => $value, "key" => $key]);
        }

        $page['listPage'] = $stack;

        return response(['data' => $page]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Page $page)
    {
        $page->fill($request->only([
            'content',
            'title',
            'slug',
            'list'
        ]));
        $id = $request->get("list");
        $name = null;
        foreach ($request->get("listPage") as $one) {
            if ($one["key"] == $id) {
                $name = $one["label"];
            }
        }
        $page->template_file = 'pages.' . $name;
        $page->save();
        return response()->json(['page' => $page]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Page $page
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy(Page $page)
    {
        $page->delete();
        return response(null, 202);
    }

    public function deleteDynamicDataPage($id)
    {
        $page = DynamicField::find($id);
        if ($page == null) {
            throw new BadRequestHttpException();
        }
        $page->forceDelete();
        return response(null, 202);
    }

    public function listPage()
    {
        $dir = base_path() . '/resources/views/pages';
        $files = scandir($dir, 1);
        $stack = [];
        foreach ($files as $key => $value) {
            if ($value != '..' && $value != '.' && $value != "defaultPageView.blade.php" && $value != "admin.blade.php")
                array_push($stack, ["label" => $value, "key" => $key]);
        }
        return response()->json(['data' => $stack]);
    }

    public function dynamicDataPage($id)
    {
        $page = ContentPage::with("dynamicData")->where("id", $id)->first();
        if ($page == null) {
            throw new BadRequestHttpException();
        }
        return response()->json(['data' => $page->dynamicData->toArray()]);
    }

    public function addDynamicData(Request $request, $id)
    {
        $data = new DynamicField();
        $data->fill($request->all());
        $data->id_page = $id;
        $data->save();
        return response(null, 202);
    }

    public function updateDynamicDataPage(Request $request, $idDynamicField)
    {
        $data = DynamicField::find($idDynamicField);
        if ($data == null) {
            return new BadRequestHttpException();
        }
        $requestData = $request->all();
        if (!empty($requestData)) {
            $data->value = $requestData["value"];
            $data->save();
            return response(null, 202);
        }

    }
}
