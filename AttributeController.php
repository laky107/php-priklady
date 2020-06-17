<?php

namespace App\Http\Controllers\Api\Product;

use App\Attribute;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AttributePostRequest;
use App\Http\Requests\Api\AttributeUpdateRequest;
use App\Http\Resources\AttributeResource;
use App\Value;
use Illuminate\Support\Arr;

class AttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return AttributeResource::collection(Attribute::with('values')->paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(AttributePostRequest $request)
    {
        $attribute = Attribute::create([
            'name' => $request->name
        ]);

        // translate attribute
        foreach ($request->stores() as $store) {
            $attribute->setTranslatedFields($store['locale'], $store['fields']);
        }

        // translate values
        foreach ($request->values as $value) {
            $createdValue = $attribute->values()->create(['value' => $value['value']]);

            if (array_key_exists('stores', $value)) {
                foreach ($value['stores'] as $store) {
                    $createdValue->setTranslatedFields($store['locale'], $store['fields']);
                }
            }
        }

        return new AttributeResource($attribute->load('values'));
    }

    /**
     * Display the specified resource.
     *
     * @param  Attribute  $attribute
     * @return \Illuminate\Http\Response
     */
    public function show(Attribute $attribute)
    {
        return new AttributeResource($attribute->load('values'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Attribute  $attribute
     * @return \Illuminate\Http\Response
     */
    public function update(AttributeUpdateRequest $request, Attribute $attribute)
    {
        $attribute->updateFromRequest($request);
        
        return new AttributeResource($attribute->load('values'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  Attribute  $attribute
     * @return \Illuminate\Http\Response
     */
    public function destroy(Attribute $attribute)
    {
        if ($attribute->values()->has('variants')->first()) {
            return response()->json([
                'message' => 'Cannot delete attribute, because it is used by a variant.'
            ], 403);
        }

        $attribute->delete();

        return response()->noContent(); 
    }

    /**
     * Validation rules
     * 
     * @return array
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|unique:attributes,name|max:1024'
        ];
    }
}
