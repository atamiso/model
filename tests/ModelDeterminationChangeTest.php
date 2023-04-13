<?php

namespace Atamso\Model\Tests;

use Atamso\Model\Model;

class ModelDeterminationChangeTest extends TestCase
{
    protected $attributes = ['id' => 1, 'name' => 'koji', 'age' => 20];

    public function testNewModel()
    {
        $model = new TestModel($this->attributes);
        $this->assertFalse($model->exists);
        $this->assertTrue($model->isDirty());
        $this->assertFalse($model->wasRecentlyCreated);
        $this->assertEquals($this->attributes, $model->toArray());
        $this->assertSame([], $model->getChanges());
        $this->assertSame($this->attributes, $model->getDirty());
    }

    public function testSaveNewModel()
    {
        $model = new TestModel($this->attributes);
        $this->assertFalse($model->exists);
        $this->assertTrue($model->isDirty());
        $this->assertFalse($model->wasRecentlyCreated);
        $this->assertEquals($this->attributes, $model->toArray());
        $this->assertSame([], $model->getChanges());
        $this->assertSame($this->attributes, $model->getDirty());

        $this->assertTrue($model->save());
        $this->assertTrue($model->exists);
        $this->assertFalse($model->isDirty());
        $this->assertTrue($model->wasRecentlyCreated);
        $this->assertEquals($this->attributes, $model->toArray());
        $this->assertSame([], $model->getChanges());
        $this->assertSame([], $model->getDirty());
        $this->assertFalse($model->wasChanged());
    }

    public function testFromOriginal()
    {
        $model = (new TestModel)->newFromOriginal($this->attributes);
        $this->assertTrue($model->exists);
        $this->assertFalse($model->isDirty());
        $this->assertFalse($model->wasRecentlyCreated);
        $this->assertEquals($this->attributes, $model->toArray());
        $this->assertSame([], $model->getChanges());
        $this->assertSame([], $model->getDirty());
    }

    public function testSaveFromOriginal()
    {
        $model = (new TestModel)->newFromOriginal($this->attributes);
        $model->fill(['name' => 'suzuki', 'age' => 40]);
        $this->assertTrue($model->isDirty());
        $this->assertFalse($model->wasRecentlyCreated);
        $this->assertEquals(['id' => 1, 'name' => 'suzuki', 'age' => 40], $model->toArray());
        $this->assertSame([], $model->getChanges());
        $this->assertSame(['name' => 'suzuki', 'age' => 40], $model->getDirty());

        $this->assertTrue($model->save());
        $this->assertTrue($model->exists);
        $this->assertFalse($model->isDirty());
        $this->assertFalse($model->wasRecentlyCreated);
        $this->assertEquals(['id' => 1, 'name' => 'suzuki', 'age' => 40], $model->toArray());
        $this->assertSame(['name' => 'suzuki', 'age' => 40], $model->getChanges());
        $this->assertSame([], $model->getDirty());
        $this->assertTrue($model->wasChanged());
    }
}

/**
 * @property string id
 * @property string name
 * @property int age
 */
class TestModel extends Model
{
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'age'
    ];
}
