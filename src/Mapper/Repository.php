<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Cycle\Mapper;

use Spiral\Cycle\Select;

/**
 * Repository provides ability to load entities and construct queries.
 */
class Repository implements RepositoryInterface
{
    /** @var Select */
    protected $selector;

    /**
     * Create repository linked to one specific selector.
     *
     * @param Select $selector
     */
    public function __construct(Select $selector)
    {
        $this->selector = $selector;
    }

    /**
     * @inheritdoc
     */
    public function findByPK($id)
    {
        return $this->find()->wherePK($id)->fetchOne();
    }

    /**
     * @inheritdoc
     */
    public function findOne(array $scope = [])
    {
        return $this->find($scope)->fetchOne();
    }

    /**
     * @inheritdoc
     */
    public function findAll(array $scope = [], array $orderBy = []): iterable
    {
        return $this->find($scope)->orderBy($orderBy)->fetchAll();
    }

    /**
     * @param array $where
     * @return Select|iterable
     */
    public function find(array $where = []): Select
    {
        return (clone $this->selector)->where($where);
    }

    /**
     * Repositories are always immutable by default.
     */
    public function __clone()
    {
        $this->selector = clone $this->selector;
    }
}