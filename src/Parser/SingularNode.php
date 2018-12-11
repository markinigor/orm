<?php
/**
 * Spiral, Core Components
 *
 * @author Wolfy-J
 */

namespace Spiral\ORM\Parser;

use Spiral\ORM\Exception\NodeException;

/**
 * Node with ability to push it's data into referenced tree location.
 */
class SingularNode extends AbstractNode
{
    /** @var string */
    protected $innerKey;

    /**
     * @param array       $columns
     * @param string      $primaryKey
     * @param string      $innerKey Inner relation key (for example user_id)
     * @param string|null $outerKey Outer (parent) relation key (for example id = parent.id)
     */
    public function __construct(array $columns, string $primaryKey, string $innerKey, string $outerKey)
    {
        parent::__construct($columns, $outerKey);
        $this->setDuplicateCriteria($primaryKey);

        $this->innerKey = $innerKey;
    }

    /**
     * {@inheritdoc}
     */
    protected function push(array &$data)
    {
        if (empty($this->parent)) {
            throw new NodeException("Unable to register data tree, parent is missing");
        }

        if (is_null($data[$this->innerKey])) {
            //No data was loaded
            return;
        }

        $this->parent->mount(
            $this->container,
            $this->outerKey,
            $data[$this->innerKey],
            $data
        );
    }
}