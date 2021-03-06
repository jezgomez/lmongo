<?php

use Mockery as m;

class LMongoEloquentModelTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();

		LMongo\Eloquent\Model::unsetEventDispatcher();
	}


	public function testAttributeManipulation()
	{
		$model = new LMongoModelStub;
		$model->name = 'foo';
		$this->assertEquals('foo', $model->name);
		$this->assertTrue(isset($model->name));
		unset($model->name);
		$this->assertFalse(isset($model->name));

		// test mutation
		$model->list_items = array('name' => 'taylor');
		$this->assertEquals(array('name' => 'taylor'), $model->list_items);
		$attributes = $model->getAttributes();
		$this->assertEquals(json_encode(array('name' => 'taylor')), $attributes['list_items']);
	}


	public function testCalculatedAttributes()
	{
		$model = new LMongoModelStub;
		$model->password = 'secret';
		$attributes = $model->getAttributes();

		// ensure password attribute was not set to null
		$this->assertFalse(array_key_exists('password', $attributes));
		$this->assertEquals('******', $model->password);
		$this->assertEquals('5ebe2294ecd0e0f08eab7690d2a6ee69', $attributes['password_hash']);
		$this->assertEquals('5ebe2294ecd0e0f08eab7690d2a6ee69', $model->password_hash);
	}


	public function testNewInstanceReturnsNewInstanceWithAttributesSet()
	{
		$model = new LMongoModelStub;
		$instance = $model->newInstance(array('name' => 'taylor'));
		$this->assertInstanceOf('LMongoModelStub', $instance);
		$this->assertEquals('taylor', $instance->name);
	}


	public function testCreateMethodSavesNewModel()
	{
		$_SERVER['__l_mongo.saved'] = false;
		$model = LMongoModelSaveStub::create(array('name' => 'taylor'));
		$this->assertTrue($_SERVER['__l_mongo.saved']);
		$this->assertEquals('taylor', $model->name);
	}


	public function testFindMethodCallsQueryBuilderCorrectly()
	{
		$result = LMongoModelFindStub::find('51116e8bd38e182e63000000');
		$this->assertEquals('foo', $result);
	}


	public function testFindMethodWithArrayCallsQueryBuilderCorrectly()
	{
		$result = LMongoModelFindManyStub::find(array('51116e8bd38e182e63000000', '51116e8bd38e182e63000001'));
		$this->assertEquals('foo', $result);
	}


	public function testWithMethodCallsQueryBuilderCorrectly()
	{
		$result = LMongoModelWithStub::with('foo', 'bar');
		$this->assertEquals('foo', $result);
	}


	public function testWithMethodCallsQueryBuilderCorrectlyWithArray()
	{
		$result = LMongoModelWithStub::with(array('foo', 'bar'));
		$this->assertEquals('foo', $result);
	}


	public function testUpdateProcess()
	{
		$model = $this->getMock('LMongoModelStub', array('newQuery', 'updateTimestamps'));
		$query = m::mock('LMongo\Eloquent\Builder');
		$query->shouldReceive('where')->once()->with('_id', 'MongoID');
		$query->shouldReceive('update')->once()->with(array('_id' => 1, 'name' => 'taylor'));
		$model->expects($this->once())->method('newQuery')->will($this->returnValue($query));
		$model->expects($this->once())->method('updateTimestamps');
		$model->setEventDispatcher($events = m::mock('Illuminate\Events\Dispatcher'));
		$events->shouldReceive('until')->once()->with('lmongo.updating: '.get_class($model), $model)->andReturn(true);
		$events->shouldReceive('fire')->once()->with('lmongo.updated: '.get_class($model), $model)->andReturn(true);

		$model->foo = 'bar';
		// make sure foo isn't synced so we can test that dirty attributes only are updated
		$model->syncOriginal();
		$model->_id = 1;
		$model->name = 'taylor';
		$model->exists = true;
		$this->assertTrue($model->save());
	}


	public function testUpdateIsCancelledIfUpdatingEventReturnsFalse()
	{
		$model = $this->getMock('LMongoModelStub', array('newQuery'));
		$query = m::mock('LMongo\Eloquent\Builder');
		$model->expects($this->once())->method('newQuery')->will($this->returnValue($query));
		$model->setEventDispatcher($events = m::mock('Illuminate\Events\Dispatcher'));
		$events->shouldReceive('until')->once()->with('lmongo.updating: '.get_class($model), $model)->andReturn(false);
		$model->exists = true;

		$this->assertFalse($model->save());
	}


	public function testUpdateProcessWithoutTimestamps()
	{
		$model = $this->getMock('LMongoModelStub', array('newQuery', 'updateTimestamps'));
		$model->timestamps = false;
		$query = m::mock('LMongo\Eloquent\Builder');
		$query->shouldReceive('where')->once()->with('_id', 'MongoID');
		$query->shouldReceive('update')->once()->with(array('_id' => 1, 'name' => 'taylor'));
		$model->expects($this->once())->method('newQuery')->will($this->returnValue($query));
		$model->expects($this->never())->method('updateTimestamps');

		$model->_id = 1;
		$model->name = 'taylor';
		$model->exists = true;
		$this->assertTrue($model->save());
	}


	public function testTimestampsAreReturnedAsObjects()
	{
		$model = new LMongoDateModelStub;
		$model->setRawAttributes(array(
			'created_at'	=> new MongoDate,
			'updated_at'	=> new MongoDate,
		));

		$this->assertInstanceOf('DateTime', $model->created_at);
		$this->assertInstanceOf('DateTime', $model->updated_at);
	}


	public function testTimestampsAreReturnedAsObjectsOnCreate()
	{
		$timestamps = array(
			'created_at' => new DateTime,
			'updated_at' => new DateTime
		);
		$model = new LMongoDateModelStub;
		LMongo\Eloquent\Model::setConnectionResolver($resolver = m::mock('LMongo\DatabaseManager'));
		$resolver->shouldReceive('connection')->andReturn($mockConnection = m::mock('StdClass'));
		$instance = $model->newInstance($timestamps);
		$this->assertInstanceOf('DateTime', $instance->updated_at);
		$this->assertInstanceOf('DateTime', $instance->created_at);
	}


	public function testDateTimeAttributesReturnNullIfSetToNull()
	{
		$timestamps = array(
			'created_at' => new DateTime,
			'updated_at' => new DateTime
		);
		$model = new LMongoDateModelStub;
		LMongo\Eloquent\Model::setConnectionResolver($resolver = m::mock('LMongo\DatabaseManager'));
		$resolver->shouldReceive('connection')->andReturn($mockConnection = m::mock('StdClass'));
		$instance = $model->newInstance($timestamps);

		$instance->created_at = null;
		$this->assertNull($instance->created_at);
	}


	public function testInsertProcess()
	{
		$model = $this->getMock('LMongoModelStub', array('newQuery', 'updateTimestamps'));
		$query = m::mock('LMongo\Eloquent\Builder');
		$query->shouldReceive('save')->once()->with(array('name' => 'taylor'))->andReturn('511221c4aa6927d812000002');
		$model->expects($this->once())->method('newQuery')->will($this->returnValue($query));
		$model->expects($this->once())->method('updateTimestamps');

		$model->setEventDispatcher($events = m::mock('Illuminate\Events\Dispatcher'));
		$events->shouldReceive('until')->once()->with('lmongo.creating: '.get_class($model), $model)->andReturn(true);
		$events->shouldReceive('fire')->once()->with('lmongo.created: '.get_class($model), $model);

		$model->name = 'taylor';
		$model->exists = false;
		$this->assertTrue($model->save());
		$this->assertEquals(new MongoID('511221c4aa6927d812000002'), $model->_id);
		$this->assertTrue($model->exists);
	}


	public function testInsertIsCancelledIfCreatingEventReturnsFalse()
	{
		$model = $this->getMock('LMongoModelStub', array('newQuery'));
		$query = m::mock('LMongo\Eloquent\Builder');
		$model->expects($this->once())->method('newQuery')->will($this->returnValue($query));
		$model->setEventDispatcher($events = m::mock('Illuminate\Events\Dispatcher'));
		$events->shouldReceive('until')->once()->with('lmongo.creating: '.get_class($model), $model)->andReturn(false);

		$this->assertFalse($model->save());
		$this->assertFalse($model->exists);
	}


	public function testDeleteProperlyDeletesModel()
	{
		$model = $this->getMock('LMongo\Eloquent\Model', array('newQuery', 'updateTimestamps'));
		$query = m::mock('stdClass');
		$query->shouldReceive('where')->once()->with('_id', 'MongoID')->andReturn($query);
		$query->shouldReceive('delete')->once();
		$model->expects($this->once())->method('newQuery')->will($this->returnValue($query));
		$model->exists = true;
		$model->id = 1;
		$model->delete();
	}


	public function testNewQueryReturnsLMongoQueryBuilder()
	{
		$conn = m::mock('LMongo\Connection');
		LMongoModelStub::setConnectionResolver($resolver = m::mock('LMongo\DatabaseManager'));
		$resolver->shouldReceive('connection')->andReturn($conn);
		$model = new LMongoModelStub;
		$builder = $model->newQuery();
		$this->assertInstanceOf('LMongo\Eloquent\Builder', $builder);
	}


	public function testGetAndsetCollectionOperations()
	{
		$model = new LMongoModelStub;
		$this->assertEquals('stub', $model->getCollection());
		$model->collection('foo');
		$this->assertEquals('foo', $model->getCollection());
	}


	public function testGetKeyReturnsValueOfPrimaryKey()
	{
		$model = new LMongoModelStub;
		$model->_id = '51116e8bd38e182e63000000';
		$this->assertEquals('51116e8bd38e182e63000000', $model->getKey());
		$this->assertEquals('_id', $model->getKeyName());
	}


	public function testConnectionManagement()
	{
		LMongoModelStub::setConnectionResolver($resolver = m::mock('LMongo\DatabaseManager'));
		$model = new LMongoModelStub;
		$model->setConnection('foo');
		$resolver->shouldReceive('connection')->once()->with('foo')->andReturn('bar');

		$this->assertEquals('bar', $model->getConnection());
	}


	public function testToArray()
	{
		$model = new LMongoModelStub;
		$model->name = 'foo';
		$model->age = null;
		$model->password = 'password1';
		$model->setHidden(array('password'));
		$model->setRelation('names', new LMongo\Eloquent\Collection(array(
			new LMongoModelStub(array('bar' => 'baz')), new LMongoModelStub(array('bam' => 'boom'))
		)));
		$model->setRelation('partner', new LMongoModelStub(array('name' => 'abby')));
		$array = $model->toArray();

		$this->assertTrue(is_array($array));
		$this->assertEquals('foo', $array['name']);
		$this->assertEquals('baz', $array['names'][0]['bar']);
		$this->assertEquals('boom', $array['names'][1]['bam']);
		$this->assertEquals('abby', $array['partner']['name']);
		$this->assertFalse(isset($array['password']));
	}


	public function testHiddenCanAlsoExcludeRelationships()
	{
		$model = new LMongoModelStub;
		$model->name = 'Taylor';
		$model->setRelation('foo', array('bar'));
		$model->setHidden(array('foo'));
		$array = $model->toArray();

		$this->assertEquals(array('name' => 'Taylor'), $array);
	}


	public function testToArraySnakeAttributes()
	{
		$model = new LMongoModelStub;
		$model->setRelation('namesList', new LMongo\Eloquent\Collection(array(
			new LMongoModelStub(array('bar' => 'baz')), new LMongoModelStub(array('bam' => 'boom'))
		)));
		$array = $model->toArray();

		$this->assertEquals('baz', $array['names_list'][0]['bar']);
		$this->assertEquals('boom', $array['names_list'][1]['bam']);

		$model = new LMongoModelCamelStub;
		$model->setRelation('namesList', new LMongo\Eloquent\Collection(array(
			new LMongoModelStub(array('bar' => 'baz')), new LMongoModelStub(array('bam' => 'boom'))
		)));
		$array = $model->toArray();

		$this->assertEquals('baz', $array['namesList'][0]['bar']);
		$this->assertEquals('boom', $array['namesList'][1]['bam']);
	}


	public function testToArrayUsesMutators()
	{
		$model = new LMongoModelStub;
		$model->list_items = array(1, 2, 3);
		$array = $model->toArray();

		$this->assertEquals(array(1, 2, 3), $array['list_items']);
	}


	public function testFillable()
	{
		$model = new LMongoModelStub;
		$model->fillable(array('name', 'age'));
		$model->fill(array('name' => 'foo', 'age' => 'bar', 'password' => 'baz'));
		$this->assertFalse(isset($model->password));
		$this->assertEquals('foo', $model->name);
		$this->assertEquals('bar', $model->age);
	}


	public function testUnderscorePropertiesAreNotFilled()
	{
		$model = new LMongoModelStub;
		$model->fill(array('_method' => 'PUT'));
		$this->assertEquals(array(), $model->getAttributes());
	}

	public function testGuarded()
	{
		$model = new LMongoModelStub;
		$model->guard(array('name', 'age'));
		$model->fill(array('name' => 'foo', 'age' => 'bar', 'votes' => 'baz'));
		$this->assertFalse(isset($model->name));
		$this->assertFalse(isset($model->age));
		$this->assertEquals('baz', $model->votes);

		$model = new LMongoModelStub;
		$model->guard(array('*'));
		$model->fill(array('name' => 'foo', 'age' => 'bar', 'votes' => 'baz'));
		$this->assertFalse(isset($model->name));
		$this->assertFalse(isset($model->age));
		$this->assertFalse(isset($model->votes));
	}


	public function testHasOneCreatesProperRelation()
	{
		$model = new LMongoModelStub;
		$this->addMockConnection($model);
		$relation = $model->hasOne('LMongoModelSaveStub');
		$this->assertEquals('l_mongo_model_stub_id', $relation->getForeignKey());

		$model = new LMongoModelStub;
		$this->addMockConnection($model);
		$relation = $model->hasOne('LMongoModelSaveStub', 'foo');
		$this->assertEquals('foo', $relation->getForeignKey());
		$this->assertTrue($relation->getParent() === $model);
		$this->assertTrue($relation->getQuery()->getModel() instanceof LMongoModelSaveStub);
	}


	public function testMorphOneCreatesProperRelation()
	{
		$model = new LMongoModelStub;
		$this->addMockConnection($model);
		$relation = $model->morphOne('LMongoModelSaveStub', 'morph');
		$this->assertEquals('morph_id', $relation->getForeignKey());
		$this->assertEquals('morph_type', $relation->getMorphType());
		$this->assertEquals('LMongoModelStub', $relation->getMorphClass());
	}


	public function testHasManyCreatesProperRelation()
	{
		$model = new LMongoModelStub;
		$this->addMockConnection($model);
		$relation = $model->hasMany('LMongoModelSaveStub');
		$this->assertEquals('l_mongo_model_stub_id', $relation->getForeignKey());

		$model = new LMongoModelStub;
		$this->addMockConnection($model);
		$relation = $model->hasMany('LMongoModelSaveStub', 'foo');
		$this->assertEquals('foo', $relation->getForeignKey());
		$this->assertTrue($relation->getParent() === $model);
		$this->assertTrue($relation->getQuery()->getModel() instanceof LMongoModelSaveStub);
	}


	public function testMorphManyCreatesProperRelation()
	{
		$model = new LMongoModelStub;
		$this->addMockConnection($model);
		$relation = $model->morphMany('LMongoModelSaveStub', 'morph');
		$this->assertEquals('morph_id', $relation->getForeignKey());
		$this->assertEquals('morph_type', $relation->getMorphType());
		$this->assertEquals('LMongoModelStub', $relation->getMorphClass());
	}


	public function testBelongsToCreatesProperRelation()
	{
		$model = new LMongoModelStub;
		$this->addMockConnection($model);
		$relation = $model->belongsToStub();
		$this->assertEquals('belongs_to_stub_id', $relation->getForeignKey());
		$this->assertTrue($relation->getParent() === $model);
		$this->assertTrue($relation->getQuery()->getModel() instanceof LMongoModelSaveStub);

		$model = new LMongoModelStub;
		$this->addMockConnection($model);
		$relation = $model->belongsToExplicitKeyStub();
		$this->assertEquals('foo', $relation->getForeignKey());
	}


	public function testMorphToCreatesProperRelation()
	{
		$model = m::mock('LMongo\Eloquent\Model[belongsTo]');
		$model->foo_type = 'FooClass';
		$model->shouldReceive('belongsTo')->with('FooClass', 'foo_id');
		$relation = $model->morphTo('foo');

		$model = m::mock('LMongoModelStub[belongsTo]');
		$model->morph_to_stub_type = 'FooClass';
		$model->shouldReceive('belongsTo')->with('FooClass', 'morph_to_stub_id');
		$relation = $model->morphToStub();
	}


	public function testBelongsToManyCreatesProperRelation()
	{
		$model = new LMongoModelStub;
		$this->addMockConnection($model);
		$relation = $model->belongsToMany('LMongoModelSaveStub');
		$this->assertEquals('l_mongo_model_stub_id', $relation->getForeignKey());
		$this->assertEquals('l_mongo_model_save_stub_id', $relation->getOtherKey());
		$this->assertTrue($relation->getParent() === $model);
		$this->assertTrue($relation->getQuery()->getModel() instanceof LMongoModelSaveStub);

		$model = new LMongoModelStub;
		$this->addMockConnection($model);
		$relation = $model->belongsToMany('LMongoModelSaveStub', 'foreign', 'other');
		$this->assertEquals('foreign', $relation->getForeignKey());
		$this->assertEquals('other', $relation->getOtherKey());
		$this->assertTrue($relation->getParent() === $model);
		$this->assertTrue($relation->getQuery()->getModel() instanceof LMongoModelSaveStub);
	}


	public function testModelsAssumeTheirName()
	{
		$model = new LMongoModelWithoutCollectionStub;
		$this->assertEquals('l_mongo_model_without_collection_stubs', $model->getCollection());

		require_once __DIR__.'/stubs/EloquentModelNamespacedStub.php';
		$namespacedModel = new Foo\Bar\EloquentModelNamespacedStub;
		$this->assertEquals('foo_bar_eloquent_model_namespaced_stubs', $namespacedModel->getCollection());
	}


	public function testTheMutatorCacheIsPopulated()
	{
		$class = new LMongoModelStub;

		$this->assertEquals(array('list_items', 'password'), $class->getMutatedAttributes());
	}


	protected function addMockConnection($model)
	{
		$resolver = m::mock('LMongo\DatabaseManager');
		$resolver->shouldReceive('connection')->andReturn(m::mock('LMongo\Connection'));

		$model->setConnectionResolver($resolver);
	}

}

class LMongoModelStub extends LMongo\Eloquent\Model {
	protected $collection = 'stub';
	public function getListItemsAttribute($value)
	{
		return json_decode($value, true);
	}
	public function setListItemsAttribute($value)
	{
		$this->attributes['list_items'] = json_encode($value);
	}
	public function getPasswordAttribute()
	{
		return '******';
	}
	public function setPasswordAttribute($value)
	{
		$this->attributes['password_hash'] = md5($value);
	}
	public function belongsToStub()
	{
		return $this->belongsTo('LMongoModelSaveStub');
	}
	public function morphToStub()
	{
		return $this->morphTo();
	}
	public function belongsToExplicitKeyStub()
	{
		return $this->belongsTo('LMongoModelSaveStub', 'foo');
	}
}

class LMongoModelCamelStub extends LMongoModelStub {
	public static $snakeAttributes = false;
}


class LMongoDateModelStub extends LMongoModelStub {
	protected $dates = array('created_at', 'updated_at');
}

class LMongoModelSaveStub extends LMongo\Eloquent\Model {
	protected $collection = 'save_stub';
	public function save() { $_SERVER['__l_mongo.saved'] = true; }
}

class LMongoModelFindStub extends LMongo\Eloquent\Model {
	public function newQuery()
	{
		$mock = m::mock('LMongo\Eloquent\Builder');
		$mock->shouldReceive('find')->once()->with('51116e8bd38e182e63000000', array())->andReturn('foo');
		return $mock;
	}
}

class LMongoModelFindManyStub extends LMongo\Eloquent\Model {
	public function newQuery()
	{
		$mock = m::mock('LMongo\Eloquent\Builder');
		$mock->shouldReceive('whereIn')->once()->with('_id', array(new MongoID('51116e8bd38e182e63000000'), new MongoID('51116e8bd38e182e63000001')))->andReturn($mock);
		$mock->shouldReceive('get')->once()->with(array())->andReturn('foo');
		return $mock;
	}
}

class LMongoModelWithStub extends LMongo\Eloquent\Model {
	public function newQuery()
	{
		$mock = m::mock('LMongo\Eloquent\Builder');
		$mock->shouldReceive('with')->once()->with(array('foo', 'bar'))->andReturn('foo');
		return $mock;
	}
}

class LMongoModelWithoutCollectionStub extends LMongo\Eloquent\Model {}