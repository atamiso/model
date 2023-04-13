<?php

namespace Atamso\Model\Features;

use Illuminate\Support\Str;
use Atamso\Model\Model;

/**
 * @mixin Model
 */
trait ModelAction
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;


    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    public function getKeyName()
    {
        return $this->primaryKey;
    }

    public function getKeyType()
    {
        return $this->keyType;
    }

    public function getTable()
    {
        return $this->table ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    public function setTable($table)
    {
        $this->table = $table;

        return $this;
    }

    public function save(array $options = [])
    {
        $this->mergeAttributesFromClassCasts();

        if ($this->exists) {
            $saved = !$this->isDirty() || $this->performUpdate();
        } else {
            $saved = $this->performInsert();
        }

        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    protected function performUpdate()
    {
        // updated_at更新を行う場合この部分に書く

        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            // 更新処理
            $this->update($dirty);
            $this->syncChanges();
        }

        return true;
    }

    public function update(array $attributes = [])
    {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes);
    }

    protected function performInsert()
    {
        // created_at更新を行う場合この部分に書く

        $attributes = $this->getAttributesForInsert();

        if ($this->getIncrementing()) {
            $this->insertAndSetId($attributes);
        } else {
            if (empty($attributes)) {
                return true;
            }
            // Insert処理
        }

        $this->exists = true;
        // wasRecentlyCreated InsertされたModelと更新されたModelの区別用フラグ
        $this->wasRecentlyCreated = true;

        return true;
    }

    protected function getAttributesForInsert()
    {
        return $this->getAttributes();
    }

    public function getIncrementing()
    {
        return $this->incrementing;
    }

    protected function insertAndSetId($attributes)
    {
        $keyName = $this->getKeyName();

        $id = 1;// ID発番処理を行う

        $this->setAttribute($keyName, $id);
    }

    protected function finishSave(array $options)
    {
        $this->syncOriginal();
    }

    public function delete()
    {
        $this->mergeAttributesFromClassCasts();
        $this->performDeleteOnModel();
    }

    protected function performDeleteOnModel()
    {
        $this->exists = false;
    }

}
