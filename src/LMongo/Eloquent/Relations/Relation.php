<?php namespace LMongo\Eloquent\Relations;

use LMongo\Eloquent\Model;
use LMongo\Eloquent\Builder;
use LMongo\Eloquent\Collection;

abstract class Relation {

	/**
	 * Model query builder instance.
	 *
	 * @var LMongo\Eloquent\Builder
	 */
	protected $query;

	/**
	 * The parent model instance.
	 *
	 * @var LMongo\Eloquent\Model
	 */
	protected $parent;

	/**
	 * The related model instance.
	 *
	 * @var LMongo\Eloquent\Model
	 */
	protected $related;

	/**
	 * Create a new relation instance.
	 *
	 * @param  LMongo\Eloquent\Builder
	 * @param  LMongo\Eloquent\Model
	 * @return void
	 */
	public function __construct(Builder $query, Model $parent)
	{
		$this->query = $query;
		$this->parent = $parent;
		$this->related = $query->getModel();

		$this->addConstraints();
	}

	/**
	 * Set the base constraints on the relation query.
	 *
	 * @return void
	 */
	abstract public function addConstraints();

	/**
	 * Set the constraints for an eager load of the relation.
	 *
	 * @param  array  $models
	 * @return void
	 */
	abstract public function addEagerConstraints(array $models);

	/**
	 * Initialize the relation on a set of models.
	 *
	 * @param  array   $models
	 * @param  string  $relation
	 * @return void
	 */
	abstract public function initRelation(array $models, $relation);

	/**
	 * Match the eagerly loaded results to their parents.
	 *
	 * @param  array   $models
	 * @param  LMongo\Eloquent\Collection  $results
	 * @param  string  $relation
	 * @return array
	 */
	abstract public function match(array $models, Collection $results, $relation);

	/**
	 * Get the results of the relationship.
	 *
	 * @return mixed
	 */
	abstract public function getResults();

	/**
	 * Remove the original where clause set by the relationship.
	 *
	 * The remaining constraints on the query will be reset and returned.
	 *
	 * @return array
	 */
	public function getAndResetWheres()
	{
		$this->removeFirstWhereClause();

		return $this->getBaseQuery()->getAndResetWheres();
	}

	/**
	 * Remove the first where clause from the relationship query.
	 *
	 * @return void
	 */
	public function removeFirstWhereClause()
	{
		array_shift($this->getBaseQuery()->wheres);
	}

	/**
	 * Get all of the primary keys for an array of models.
	 *
	 * @param  array  $models
	 * @return array
	 */
	protected function getKeys(array $models)
	{
		return array_values(array_map(function($value)
		{
			return $value->getKey();

		}, $models));
	}

	/**
	 * Get the underlying query for the relation.
	 *
	 * @return LMongo\Eloquent\Builder
	 */
	public function getQuery()
	{
		return $this->query;
	}

	/**
	 * Get the base query builder driving the model builder.
	 *
	 * @return LMongo\Query\Builder
	 */
	public function getBaseQuery()
	{
		return $this->query->getQuery();
	}

	/**
	 * Get the parent model of the relation.
	 *
	 * @return LMongo\Eloquent\Model
	 */
	public function getParent()
	{
		return $this->parent;
	}

	/**
	 * Get the related model of the relation.
	 *
	 * @return LMongo\Eloquent\Model
	 */
	public function getRelated()
	{
		return $this->related;
	}

	/**
	 * Get the name of the "created at" column.
	 *
	 * @return string
	 */
	public function createdAt()
	{
		return $this->parent->getCreatedAtColumn();
	}

	/**
	 * Get the name of the "updated at" column.
	 *
	 * @return string
	 */
	public function updatedAt()
	{
		return $this->parent->getUpdatedAtColumn();
	}

	/**
	 * Handle dynamic method calls to the relationship.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		$result = call_user_func_array(array($this->query, $method), $parameters);

		if ($result === $this->query) return $this;

		return $result;
	}

}