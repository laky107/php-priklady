<?php

namespace App\Traits;

use App\Store;
use Illuminate\Support\Arr;

trait Localizable {

    /**
     * Localize product to a given locale
     * 
     * @param  string $locale Locale to translate to
     * @return App\Product  Localized product
     */
    public function localize(string $locale)
    {
        // Do not retrieve for fallback locale
        // Return object right away instead
        if (app()->getLocale() === config('app.fallback_locale')) {
            return $this;
        }

        $store = Store::getByLocaleOrFail($locale);

        $this->localizeModel($store);

        return $this;
    }

    protected function localizeModel($store)
    {
        if ($translatedFields = $this->getTranslatedFields($store->locale)) {
            foreach ($translatedFields as $key => $field) {
                $this->$key = $field;
            }
        }
    }

    /**
     * Save product translation using BLOB column
     * 
     * @param string $locale [description]
     * @param array  $data   [description]
     */
    public function setTranslatedFields(string $locale, array $data)
    {
        $currentLocales = unserialize($this->translation);

        $currentLocales[$locale] = $data;

        $this->attributes['translation'] = serialize($currentLocales);
        
        $this->save();
    }

    public function overwriteTranslations($data)
    {
        if (! property_exists(self::class, 'translate')) {
            throw new \Exception('Property translate has not been set for class ' . self::class);
        }

        $availableLocales = Store::select('locale')
            ->distinct()
            ->pluck('locale')
            ->toArray();

        $translation = [];
        foreach ($data as $locale => $fields) {
            if (in_array($locale, $availableLocales) 
                && Arr::has($fields, $this->translate))
            {
                $translation[$locale] = Arr::only($fields, $this->translate);
            }
        }
        
        $this->attributes['translation'] = serialize($translation);

        $this->save();
    }

    private function getTranslations()
    {
        return unserialize($this->translation);
    }

    public function isLocalized(string $locale)
    {
        return $this->getLocales() 
            ? array_key_exists($locale, $this->getTranslations())
            : false;
    }

    public function getTranslatedFields(string $locale)
    {
        return $this->isLocalized($locale) 
            ? $this->getTranslations()[$locale]
            : null;
    }

    public function getLocales()
    {
        return $this->getTranslations()
            ? array_keys($this->getTranslations())
            : null;
    }

    public function getStores()
    {
        return $this->getLocales()
            ? Store::whereIn('locale', $this->getLocales())
                ->get()
            : collect();
    }

}