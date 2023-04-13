<?php

namespace Atamso\Model\Features;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

/**
 * @property array attributes
 */
trait CastAttribute
{
    /**
     * The built-in, primitive cast types supported by Eloquent.
     *
     * @var array
     */
    protected static $primitiveCastTypes = [
        'array',
        'bool',
        'boolean',
        'collection',
        'custom_datetime',
        'date',
        'datetime',
        'decimal',
        'double',
        'float',
        'int',
        'integer',
        'json',
        'object',
        'real',
        'string',
        'timestamp',
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];
    /**
     * The attributes that have been cast using custom classes.
     *
     * @var array
     */
    protected $classCastCache = [];

    public function mergeCasts($casts)
    {
        $this->casts = array_merge($this->casts, $casts);

        return $this;
    }

    public function fillJsonAttribute($key, $value)
    {
        [$key, $path] = explode('->', $key, 2);

        $value = $this->asJson($this->getArrayAttributeWithValue(
            $path, $key, $value
        ));
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Encode the given value as JSON.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function asJson($value)
    {
        return json_encode($value);
    }

    protected function getArrayAttributeWithValue($path, $key, $value)
    {
        return tap($this->getArrayAttributeByKey($key), function (&$array) use ($path, $value) {
            Arr::set($array, str_replace('->', '.', $path), $value);
        });
    }

    protected function getArrayAttributeByKey($key)
    {
        if (!isset($this->attributes[$key])) {
            return [];
        }

        return $this->fromJson($this->attributes[$key]);
    }

    public function fromJson($value, $asObject = false)
    {
        return json_decode($value, !$asObject);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function isJsonCastable($key)
    {
        $castables = ['array', 'json', 'object', 'collection'];

        return $this->hasCast($key) &&
            in_array($this->getCastType($key), $castables, true);
    }

    protected function hasCast($key, $types = null)
    {
        if (array_key_exists($key, $this->getCasts())) {
            return !$types || in_array($this->getCastType($key), (array)$types, true);
        }

        return false;
    }

    public function getCasts()
    {
        return $this->casts;
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param string $key
     *
     * @return string
     */
    protected function getCastType($key)
    {
        return strtolower(trim($this->casts[$key]));
    }

    protected function addCastAttributesToArray(array $attributes, array $mutatedAttributes)
    {
        foreach ($this->getCasts() as $key => $value) {
            if (!array_key_exists($key, $attributes) ||
                in_array($key, $mutatedAttributes, true) ||
                $this->isClassCastable($key)) {
                continue;
            }

            $attributes[$key] = $this->castAttribute(
                $key, $attributes[$key]
            );

            if ($attributes[$key] && in_array($value, ['date', 'datetime', 'immutable_date', 'immutable_datetime'])) {
                $attributes[$key] = $this->serializeDate($attributes[$key]);
            }

            if ($attributes[$key] instanceof Arrayable) {
                $attributes[$key] = $attributes[$key]->toArray();
            }
        }

        return $attributes;
    }

    /**
     * Determine if the given key is cast using a custom class.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function isClassCastable($key)
    {
        return array_key_exists($key, $this->getCasts()) &&
            class_exists($class = $this->parseCasterClass($this->getCasts()[$key])) &&
            !in_array($class, static::$primitiveCastTypes, true);
    }

    protected function parseCasterClass($class)
    {
        return !str_contains($class, ':')
            ? $class
            : explode(':', $class, 2)[0];
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        $castType = $this->getCastType($key);

        if (is_null($value) && in_array($castType, static::$primitiveCastTypes, true)) {
            return null;
        }

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'real':
            case 'float':
            case 'double':
                return (float)$value;
            case 'string':
                return (string)$value;
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new BaseCollection($this->fromJson($value));
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'immutable_date':
                return $this->asDate($value)->toImmutable();
            case 'immutable_custom_datetime':
            case 'immutable_datetime':
                return $this->asDateTime($value)->toImmutable();
            case 'timestamp':
                return $this->asTimestamp($value);

        }

        if ($this->isClassCastable($key)) {
            return $this->getClassCastableAttributeValue($key);
        }

        return $value;
    }

    /**
     * Cast the given attribute using a custom cast class.
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function getClassCastableAttributeValue($key)
    {
        if (isset($this->classCastCache[$key])) {
            return $this->classCastCache[$key];
        }

        $caster = $this->resolveCasterClass($key);

        return $this->classCastCache[$key] = $caster instanceof CastsInboundAttributes
            ? $this->attributes[$key]
            : $caster->get($this, $key, $this->attributes[$key] ?? null, $this->attributes);
    }

    /**
     * Resolve the custom caster class for a given key.
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function resolveCasterClass($key)
    {
        $castType = $this->getCasts()[$key];

        $arguments = [];

        if (is_string($castType) && Str::contains($castType, ':')) {
            $segments = explode(':', $castType, 2);
            $castType = $segments[0];
            $arguments = explode(',', $segments[1]);
        }

        if (is_subclass_of($castType, Castable::class)) {
            $castType = $castType::castUsing($arguments);
        }

        if (is_object($castType)) {
            return $castType;
        }

        return new $castType(...$arguments);
    }

    protected function castAttributeAsJson($key, $value)
    {
        $value = $this->asJson($value);

        if ($value === false) {
            throw JsonEncodingException::forAttribute(
                $this, $key, json_last_error_msg()
            );
        }

        return $value;
    }

    /**
     * Merge the cast class attributes back into the model.
     *
     * @return void
     */
    protected function mergeAttributesFromClassCasts()
    {
        foreach ($this->classCastCache as $key => $value) {
            $caster = $this->resolveCasterClass($key);

            /** @noinspection SlowArrayOperationsInLoopInspection */
            $this->attributes = array_merge(
                $this->attributes,
                $caster instanceof CastsInboundAttributes
                    ? [$key => $value]
                    : $this->normalizeCastClassResponse($key, $caster->set($this, $key, $value, $this->attributes))
            );
        }
    }

    /**
     * Normalize the response from a custom class caster.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return array
     */
    protected function normalizeCastClassResponse($key, $value)
    {
        return is_array($value) ? $value : [$key => $value];
    }

    /**
     * Set the value of a class castable attribute.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    protected function setClassCastableAttribute($key, $value)
    {
        if (is_null($value)) {
            $this->attributes = array_merge($this->attributes, array_map(
                function () {
                },
                $this->normalizeCastClassResponse($key, $this->resolveCasterClass($key)->set(
                    $this, $key, $this->{$key}, $this->attributes
                ))
            ));
        } else {
            $this->attributes = array_merge(
                $this->attributes,
                $this->normalizeCastClassResponse($key, $this->resolveCasterClass($key)->set(
                    $this, $key, $value, $this->attributes
                ))
            );
        }

        unset($this->classCastCache[$key]);
    }

}
