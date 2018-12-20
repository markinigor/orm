<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
declare(strict_types=1);

namespace Spiral\Cycle\Select\Traits;

use Spiral\Cycle\Select\QueryProxy;
use Spiral\Database\Query\SelectQuery;

/**
 * Provides the ability to configure relation specific where conditions.
 */
trait WhereTrait
{
    /**
     * @param SelectQuery $query
     * @param string      $table  Table name to be automatically inserted into where conditions at place of {@}.
     * @param string      $target Query target section (accepts: where, having, onWhere, on)
     * @param array       $where  Where conditions in a form or short array form.
     * @return SelectQuery
     */
    private function setWhere(SelectQuery $query, string $table, string $target, array $where = null): SelectQuery
    {
        if (empty($where)) {
            //No conditions, nothing to do
            return $query;
        }

        $proxy = new QueryProxy($this->orm, $query, $this);
        $proxy->setForward($target)->where($where);

        return $proxy->getQuery();
    }
}