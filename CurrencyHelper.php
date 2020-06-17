<?php


namespace App\Helpers\Backend;


use App\Currency;
use Illuminate\Support\Collection;
use Orchestra\Parser\Xml\Facade as XmlParser;

class CurrencyHelper
{
    public const PLACES = 2;

    public static function convert($item, $currencyId)
    {
        if (!Currency::isDefault($currencyId)) {

            $actual = self::actualCurrency();
            return self::convertPrice($item, $actual);
        }
        return $item;
    }

    private static function convertPrice(float $item, Currency $to)
    {
        return round(($item * $to->rate()) * $to->additional(), self::PLACES, PHP_ROUND_HALF_UP);
    }


    public static function actualCurrency(): Currency
    {
        $multi = app("controller.multistore")->actualMultistore();
        if (empty($multi)) {
            return Currency::default();
        }
        return $multi->currency()->first();
    }

    public static function sum(array $array)
    {
        return round(collect($array)->sum(function ($one) {
            return $one;
        }));
    }

    public static function format($price): float
    {
        if (is_string($price)) {
            $price = floatval($price);
        }
        return round($price, self::PLACES, PHP_ROUND_HALF_UP);

    }

    public static function addTax($price, int $int): float
    {
        return round($price * (($int / 100) + 1), self::PLACES, PHP_ROUND_HALF_UP);
    }

    public static function importNewCurrency()
    {
        $url = "http://www.nbs.sk/_img/Documents/_KurzovyListok/KLVCM/AKT_KLVCM_EX.XML";

        /** @var \SimpleXMLElement $text */
        $text = file_get_contents($url);
        $data = simplexml_load_string($text);
        $list = collect($data->xpath("//rateList/rate"));
        Currency::all()->each(function (Currency $currency) use ($list) {
            if (!$currency->isDefault($currency->id)) {
                $val = self::searchCurrency($currency->short_key, $list);
                $currency->rate = $val;
                $currency->save();
            }
        });
    }

    private static function searchCurrency($code, Collection $list)
    {
        $itemFound = null;
        $list->each((function ($item, $key) use ($code, &$itemFound) {
            if ($item->ccyCode->__toString() == $code) {
                $itemFound = $item;
            }
        }));
        return $itemFound->value->__toString();
    }
}