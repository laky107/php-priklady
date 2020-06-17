<?php

namespace App\Http\Controllers;

use App\Menu;
use App\Menu\MenuItem;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response(Menu::whereNull('id_parent')->all());
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $menu = new Menu($request->only([
            'id_parent',
            'title',
            'path',
            'slug',
        ]));
        $menu->save();
        collect($request->input('children', []))->map(function($s) use($menu) {
            $m = new Menu($s);
            $m->id_parent = $menu->id;
            $m->save();
            return $m;
        });
        return response()->json(['data' => $menu]);
    }

    /**
     * Display the specified resource.
     *
     * @param Menu $menu
     * @return \Illuminate\Http\Response
     */
    public function show(Menu $menu)
    {
        $menu = Menu::with('children')->find($menu->id);
        return response(['data' => $menu]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param Menu $menu
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Menu $menu)
    {
        $menu->fill($request->only([
            'id',
            'id_parent',
            'title',
            'path',
            'slug',
        ]));
        $menu->children()->delete();
        $menu->save();
        collect($request->input('children', []))->map(function($s) use($menu) {
            $m = new Menu($s);
            $m->id_parent = $menu->id;
            $m->save();
            return $m;
        });
        return response()->json(['menu' => $menu]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Menu $menu
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy(Menu $menu)
    {
        $menu->delete();
        return response(null, 202);
    }

    public function itemsForMenu()
    {
        return response()->json([
            'data' => array_flatten(array_map(function($class) {
                return call_user_func([$class, 'all'])->map(function($item) {
                    return $item->transform();
                });
            }, config('app.menu.items')), 1)
        ]);
    }
}
