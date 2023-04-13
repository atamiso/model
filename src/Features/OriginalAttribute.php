<?php

namespace Atamso\Model\Features;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Support\Arr;
use Atamso\Model\Model;

/**
 * @mixin Model
 */
trait OriginalAttribute
{

    /**
     * The model attribute's original state.
     *
     * @var array
     */
    protected $original = [];

    protected $changes = [];

    public function getOriginal($key = null, $default = null)
    {
        return (new static)->setRawAttributes(
            $this->original, true
        )->getOriginalWithoutRewindingModel($key, $default);
    }

    protected function getOriginalWithoutRewindingModel($key = null, $default = null)
    {
        if ($key) {
            return $this->transformModelValue(
                $key, Arr::get($this->original, $key, $default)
            );
        }

        return collect($this->original)->mapWithKeys(function ($value, $key) {
            return [$key => $this->transformModelValue($key, $value)];
        })->all();
    }

    protected function transformModelValue($key, $value)
    {
        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependent upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    public function getRawOriginal($key = null, $default = null)
    {
        return Arr::get($this->original, $key, $default);
    }

    /**
     * 現在モデルに設定されている値をオリジナルに入れる
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->getAttributes();

        return $this;
    }

    public function syncOriginalAttribute($attribute)
    {
        return $this->syncOriginalAttributes($attribute);
    }

    public function syncOriginalAttributes($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $modelAttributes = $this->getAttributes();

        foreach ($attributes as $attribute) {
            $this->original[$attribute] = $modelAttributes[$attribute];
        }

        return $this;
    }

    public function syncChanges()
    {
        $this->changes = $this->getDirty();

        return $this;
    }

    public function getDirty()
    {
        $dirty = [];

        foreach ($this->getAttributes() as $key => $value) {
            if (!$this->originalIsEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function originalIsEquivalent($key)
    {
        if (!array_key_exists($key, $this->original)) {
            return false;
        }

        $attribute = Arr::get($this->attributes, $key);
        $original = Arr::get($this->original, $key);

        if ($attribute === $original) {
            return true;
        }

        if (is_null($attribute)) {
            return false;
        }

        if ($this->isDateAttribute($key) || $this->isDateCastableWithCustomFormat($key)) {
            return $this->fromDateTime($attribute) ===
                $this->fromDateTime($original);
        }

        if ($this->hasCast($key, ['object', 'collection'])) {
            return $this->fromJson($attribute) ===
                $this->fromJson($original);
        }

        if ($this->hasCast($key, ['real', 'float', 'double'])) {
            if ($original !== null) {
                return false;
            }

            return abs($this->castAttribute($key, $attribute) - $this->castAttribute($key, $original)) < PHP_FLOAT_EPSILON * 4;
        }

        if ($this->hasCast($key, static::$primitiveCastTypes)) {
            return $this->castAttribute($key, $attribute) ===
                $this->castAttribute($key, $original);
        }

        if ($this->isClassCastable($key) && in_array($this->getCasts()[$key], [AsArrayObject::class, AsCollection::class], true)) {
            return $this->fromJson($attribute) === $this->fromJson($original);
        }

        return is_numeric($attribute) && is_numeric($original)
            && strcmp((string)$attribute, (string)$original) === 0;
    }

    public function isClean($attributes = null)
    {
        return !$this->isDirty(...func_get_args());
    }

    /**
     * @param array|string|null $attributes
     *
     * @return bool
     */
    public function isDirty($attributes = null)
    {
        return $this->hasChanges(
            $this->getDirty(), is_array($attributes) ? $attributes : func_get_args()
        );
    }

    protected function hasChanges($changes, $attributes = null)
    {
        if (empty($attributes)) {
            return count($changes) > 0;
        }

        foreach (Arr::wrap($attributes) as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    public function wasChanged($attributes = null)
    {
        return $this->hasChanges(
            $this->getChanges(), is_array($attributes) ? $attributes : func_get_args()
        );
    }

    public function getChanges()
    {
        return $this->changes;
    }

}
