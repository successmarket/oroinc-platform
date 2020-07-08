<?php

namespace Oro\Component\ChainProcessor;

/**
 * The container for key/value pairs.
 */
class ParameterBag extends AbstractParameterBag
{
    /** @var array [key => value, ...] */
    private $items = [];

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return \array_key_exists($key, $this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        return $this->items[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        $this->items[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        unset($this->items[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return $this->items;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->items = [];
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return \count($this->items);
    }
}
