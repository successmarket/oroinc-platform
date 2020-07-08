<?php

namespace Oro\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The trait that allows a generic method to find and sort service by priority option in the tag
 * and then build and array of found services keyed by keys received by name option in the tag.
 */
trait PriorityTaggedLocatorTrait
{
    use TaggedServiceTrait;

    /**
     * @param string           $tagName
     * @param string           $nameAttribute
     * @param ContainerBuilder $container
     *
     * @return Reference[] [service name => service reference, ...]
     */
    private function findAndSortTaggedServices(
        string $tagName,
        string $nameAttribute,
        ContainerBuilder $container
    ): array {
        $items = [];
        $taggedServices = $container->findTaggedServiceIds($tagName, true);
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $items[$this->getPriorityAttribute($attributes)][] = [
                    $this->getRequiredAttribute($attributes, $nameAttribute, $id, $tagName),
                    $id
                ];
            }
        }

        $services = [];
        if ($items) {
            $items = $this->sortByPriorityAndFlatten($items);
            foreach ($items as list($key, $id)) {
                if (!isset($services[$key])) {
                    $services[$key] = new Reference($id);
                }
            }
        }

        return $services;
    }

    /**
     * @param string           $tagName
     * @param ContainerBuilder $container
     * @param string|null      $nameAttribute
     *
     * @return Reference[] [service name => service reference, ...]
     */
    private function findAndSortTaggedServicesWithOptionalNameAttribute(
        string $tagName,
        ContainerBuilder $container,
        string $nameAttribute = null
    ): array {
        $items = [];
        $taggedServices = $container->findTaggedServiceIds($tagName, true);
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $key = $nameAttribute
                    ? $this->getAttribute($attributes, $nameAttribute, $id)
                    : $id;
                $items[$this->getPriorityAttribute($attributes)][] = [$key, $id];
            }
        }

        $services = [];
        if ($items) {
            $items = $this->sortByPriorityAndFlatten($items);
            foreach ($items as list($key, $id)) {
                if (!isset($services[$key])) {
                    $services[$key] = new Reference($id);
                }
            }
        }

        return $services;
    }

    /**
     * @param string           $tagName
     * @param \Closure         $handler function (array $attributes, string $serviceId, string $tagName): array
     * @param ContainerBuilder $container
     *
     * @return array [services, items]
     *               services - [service name => service reference, ...]
     *               items - [[value, ...], ...]
     */
    private function findAndSortTaggedServicesWithHandler(
        string $tagName,
        \Closure $handler,
        ContainerBuilder $container
    ): array {
        $services = [];
        $items = [];
        $taggedServices = $container->findTaggedServiceIds($tagName, true);
        foreach ($taggedServices as $id => $tags) {
            $services[$id] = new Reference($id);
            foreach ($tags as $attributes) {
                $items[$this->getPriorityAttribute($attributes)][] = $handler($attributes, $id, $tagName);
            }
        }
        $items = $this->sortByPriorityAndFlatten($items);

        return [$services, $items];
    }

    /**
     * @param string           $tagName
     * @param string           $nameAttribute
     * @param ContainerBuilder $container
     *
     * @return Reference[] [service name => service reference, ...]
     *
     * @deprecated use {@see findAndSortTaggedServices} for new tags
     */
    private function findAndInverseSortTaggedServices(
        string $tagName,
        string $nameAttribute,
        ContainerBuilder $container
    ): array {
        $items = [];
        $taggedServices = $container->findTaggedServiceIds($tagName, true);
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $items[$this->getPriorityAttribute($attributes)][] = [
                    $this->getRequiredAttribute($attributes, $nameAttribute, $id, $tagName),
                    $id
                ];
            }
        }

        $services = [];
        if ($items) {
            $items = $this->inverseSortByPriorityAndFlatten($items);
            foreach ($items as list($key, $id)) {
                if (!isset($services[$key])) {
                    $services[$key] = new Reference($id);
                }
            }
        }

        return $services;
    }

    /**
     * @param string           $tagName
     * @param \Closure         $handler function (array $attributes, string $serviceId, string $tagName): array
     * @param ContainerBuilder $container
     *
     * @return array [services, items]
     *               services - [service name => service reference, ...]
     *               items - [[value, ...], ...]
     *
     * @deprecated use {@see findAndSortTaggedServicesWithHandler} for new tags
     */
    private function findAndInverseSortTaggedServicesWithHandler(
        string $tagName,
        \Closure $handler,
        ContainerBuilder $container
    ): array {
        $services = [];
        $items = [];
        $taggedServices = $container->findTaggedServiceIds($tagName, true);
        foreach ($taggedServices as $id => $tags) {
            $services[$id] = new Reference($id);
            foreach ($tags as $attributes) {
                $items[$this->getPriorityAttribute($attributes)][] = $handler($attributes, $id, $tagName);
            }
        }
        $items = $this->inverseSortByPriorityAndFlatten($items);

        return [$services, $items];
    }
}
