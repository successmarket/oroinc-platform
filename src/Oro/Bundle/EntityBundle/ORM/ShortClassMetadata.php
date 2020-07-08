<?php

namespace Oro\Bundle\EntityBundle\ORM;

/**
 * Represents a brief information about a manageable entity.
 */
class ShortClassMetadata implements \Serializable
{
    /**
     * READ-ONLY: The name of the entity class.
     *
     * @var string
     */
    public $name;

    /**
     * READ-ONLY: Whether this class describes the mapping of a mapped superclass.
     *
     * @var bool
     */
    public $isMappedSuperclass;

    /**
     * READ-ONLY: Whether this class has at least one association.
     *
     * @var bool
     */
    public $hasAssociations;

    /**
     * @param string $name
     * @param bool   $isMappedSuperclass
     * @param bool   $hasAssociations
     */
    public function __construct(string $name, bool $isMappedSuperclass = false, bool $hasAssociations = false)
    {
        $this->name = $name;
        $this->isMappedSuperclass = $isMappedSuperclass;
        $this->hasAssociations = $hasAssociations;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        $flag = 0;
        if ($this->isMappedSuperclass) {
            $flag |= 1;
        }
        if ($this->hasAssociations) {
            $flag |= 2;
        }

        return serialize([$this->name, $flag]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        [$this->name, $flag] = unserialize($serialized);
        $this->isMappedSuperclass = ($flag & 1) !== 0;
        $this->hasAssociations = ($flag & 2) !== 0;
    }

    /**
     * @param array $data
     *
     * @return ShortClassMetadata
     */
    // @codingStandardsIgnoreStart
    public static function __set_state($data)
    {
        return new ShortClassMetadata(
            $data['name'],
            $data['isMappedSuperclass'],
            $data['hasAssociations']
        );
    }
    // @codingStandardsIgnoreEnd
}
