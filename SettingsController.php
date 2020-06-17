<?php

namespace App\Http\Controllers;

use App\GlobalSettings;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function fetch()
    {
        return response()->json([
            'settings' => setting()->all()
        ]);
    }

    public function update(Request $request)
    {
        setting($request->input('settings', []))->save();
        foreach($request->input('settings', []) as $key=>$setting){
            GlobalSettings::updateOrCreate(
                [ "name" => $key ],
                [ "name" => $key, "value" => is_array($setting) ? json_encode($setting) : $setting]
            );
        }
        return response('', 202);
    }

    public function saveHereukaApiKey(Request $request, $name)
    {
        $data = $request->all();

        $settings = GlobalSettings::updateOrCreate(
            [ "name" => $name ],
            [ "name" => $name, "value" => $data['value']]
        );

        if ($settings){
            return response()->json([],200);
        }

        return response()->json([],400);
    }

    public function hereukaApiKey($name)
    {
        $setting = GlobalSettings::where('name', $name)->first();
        return response()->json([
            'setting' => $setting
        ],200);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPopUpData()
    {
        $names = ['popupShowTime', 'popupContent'];
        $result = [];
        foreach ($names as $name){
            $setting_for_popup = GlobalSettings::where('name', $name)->first();
            $result[$name] = $setting_for_popup->value;
        }

        return response()->json([
            'popup_data' => $result
        ],200);
    }
}
