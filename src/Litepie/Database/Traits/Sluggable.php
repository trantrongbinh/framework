<?php

namespace Litepie\Database\Traits;

use Exception;
use  Illuminate\Support\Str;

trait Sluggable
{
    /**
     * @var array List of attributes to automatically generate unique URL names (slugs) for.
     *
     * protected $slugs = [];
     */
    protected $slugs = [];

    /**
     * Boot the sluggable trait for a model.
     *
     * @return void
     */
    public static function bootSluggable()
    {
        if (!property_exists(get_called_class(), 'slugs')) {
            throw new Exception(sprintf('You must define a $slugs property in %s to use the Sluggable trait.', get_called_class()));
        }

        /*
         * Set slugged attributes on new records
         */
        static::creating(function ($model) {
            $model->slugAttributes();
        });
    }

    /**
     * Adds slug attributes to the dataset, used before saving.
     *
     * @return void
     */
    public function slugAttributes()
    {
        foreach ($this->slugs as $slugAttribute => $sourceAttributes) {
            $this->setSluggedValue($slugAttribute, $sourceAttributes);
        }
    }

    /**
     * Sets a single slug attribute value.
     *
     * @param string $slugAttribute    Attribute to populate with the slug.
     * @param mixed  $sourceAttributes Attribute(s) to generate the slug from.
     *                                 Supports dotted notation for relations.
     * @param int    $maxLength        Maximum length for the slug not including the counter.
     *
     * @return string The generated value.
     */
    public function setSluggedValue($slugAttribute, $sourceAttributes, $maxLength = 240)
    {
        if (!isset($this->{$slugAttribute}) || !strlen($this->{$slugAttribute})) {
            if (!is_array($sourceAttributes)) {
                $sourceAttributes = [$sourceAttributes];
            }

            $slugArr = [];
            foreach ($sourceAttributes as $attribute) {
                $slugArr[] = $this->getSluggableSourceAttributeValue($attribute);
            }

            $slug = implode(' ', $slugArr);
            $slug = substr($slug, 0, $maxLength);
            $slug = Str::slug($slug, $this->getSluggableSeparator());
        } else {
            $slug = $this->{$slugAttribute};
        }

        return $this->{$slugAttribute} = $this->getSluggableUniqueAttributeValue($slugAttribute, $slug);
    }

    /**
     * Ensures a unique attribute value, if the value is already used a counter suffix is added.
     *
     * @param string $name  The database column name.
     * @param value  $value The desired column value.
     *
     * @return string A safe value that is unique.
     */
    protected function getSluggableUniqueAttributeValue($name, $value)
    {
        $counter = 1;
        $separator = $this->getSluggableSeparator();

        // Remove any existing suffixes
        $_value = preg_replace('/'.preg_quote($separator).'[0-9]+$/', '', trim($value));

        while ($this->newQuery()->where($name, $_value)->count() > 0) {
            $counter++;
            $_value = $value.$separator.$counter;
        }

        return $_value;
    }

    /**
     * Get an attribute relation value using dotted notation.
     * Eg: author.name.
     *
     * @return mixed
     */
    protected function getSluggableSourceAttributeValue($key)
    {
        if (strpos($key, '.') === false) {
            return $this->getAttribute($key);
        }

        $keyParts = explode('.', $key);
        $value = $this;
        foreach ($keyParts as $part) {
            if (!isset($value[$part])) {
                return;
            }

            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Override the default slug separator.
     *
     * @return string
     */
    public function getSluggableSeparator()
    {
        return defined('static::SLUG_SEPARATOR') ? static::SLUG_SEPARATOR : '-';
    }

    /**
     * Override the default slug separator.
     *
     * @return string
     */
    public function findBySlug($slug, $columns = ['*'])
    {
        return $this->whereSlug($slug)->first($columns);
    }
}
