<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Normalizer;

use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\ApiBundle\DataTransformer\DataTransformerRegistry;
use Oro\Bundle\ApiBundle\Form\DataTransformer\DateTimeToStringTransformer;
use Oro\Bundle\ApiBundle\Normalizer\ConfigNormalizer;
use Oro\Bundle\ApiBundle\Normalizer\DateTimeNormalizer;
use Oro\Bundle\ApiBundle\Normalizer\ObjectNormalizer;
use Oro\Bundle\ApiBundle\Normalizer\ObjectNormalizerRegistry;
use Oro\Bundle\ApiBundle\Processor\ApiContext;
use Oro\Bundle\ApiBundle\Request\DataType;
use Oro\Bundle\ApiBundle\Request\RequestType;
use Oro\Bundle\ApiBundle\Tests\Unit\Fixtures\Entity;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\ApiBundle\Util\EntityDataAccessor;
use Oro\Bundle\ApiBundle\Util\RequestExpressionMatcher;
use Oro\Component\EntitySerializer\DataNormalizer;
use Oro\Component\EntitySerializer\DataTransformer;
use Oro\Component\EntitySerializer\SerializationHelper;
use Oro\Component\Testing\Unit\TestContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PlainObjectNormalizerTest extends \PHPUnit\Framework\TestCase
{
    /** @var ObjectNormalizer */
    private $objectNormalizer;

    protected function setUp(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects(self::any())
            ->method('getManagerForClass')
            ->willReturn(null);

        $requestExpressionMatcher = new RequestExpressionMatcher();
        $dataTransformerRegistry = new DataTransformerRegistry(
            [DataType::DATETIME => [['transformer1', null]]],
            TestContainerBuilder::create()
                ->add('transformer1', new DateTimeToStringTransformer())
                ->getContainer($this),
            $requestExpressionMatcher
        );
        $this->objectNormalizer = new ObjectNormalizer(
            new ObjectNormalizerRegistry(
                [['normalizer1', \DateTimeInterface::class, null]],
                TestContainerBuilder::create()
                    ->add('normalizer1', new DateTimeNormalizer($dataTransformerRegistry))
                    ->getContainer($this),
                $requestExpressionMatcher
            ),
            new DoctrineHelper($doctrine),
            new SerializationHelper(new DataTransformer($this->createMock(ContainerInterface::class))),
            new EntityDataAccessor(),
            new ConfigNormalizer(),
            new DataNormalizer()
        );
    }

    /**
     * @param mixed $object
     *
     * @return mixed
     */
    private function normalizeObject($object)
    {
        $normalizedObjects = $this->objectNormalizer->normalizeObjects(
            [$object],
            null,
            [ApiContext::REQUEST_TYPE => new RequestType([RequestType::REST])]
        );

        return reset($normalizedObjects);
    }

    /**
     * @return Entity\Product
     */
    private function createProductObject()
    {
        $product = new Entity\Product();
        $product->setId(123);
        $product->setName('product_name');
        $product->setUpdatedAt(new \DateTime('2015-12-01 10:20:30', new \DateTimeZone('UTC')));

        $category = new Entity\Category('category_name');
        $category->setLabel('category_label');
        $product->setCategory($category);

        $owner = new Entity\User();
        $owner->setId(456);
        $owner->setName('user_name');
        $ownerCategory = new Entity\Category('owner_category_name');
        $ownerCategory->setLabel('owner_category_label');
        $owner->setCategory($ownerCategory);
        $ownerGroup1 = new Entity\Group();
        $ownerGroup1->setId(11);
        $ownerGroup1->setName('owner_group1');
        $owner->addGroup($ownerGroup1);
        $ownerGroup2 = new Entity\Group();
        $ownerGroup2->setId(22);
        $ownerGroup2->setName('owner_group2');
        $owner->addGroup($ownerGroup2);
        $owner->addProduct($product);

        return $product;
    }

    public function testNormalizeSimpleObject()
    {
        $object = new Entity\Group();
        $object->setId(123);
        $object->setName('test_name');

        $result = $this->normalizeObject($object);

        self::assertEquals(
            [
                'id'   => 123,
                'name' => 'test_name'
            ],
            $result
        );
    }

    public function testNormalizeObjectWithNullToOneRelations()
    {
        $product = new Entity\Product();
        $product->setId(123);
        $product->setName('product_name');

        $result = $this->normalizeObject($product);

        self::assertEquals(
            [
                'id'            => 123,
                'name'          => 'product_name',
                'updatedAt'     => null,
                'category'      => null,
                'owner'         => null,
                'price'         => null,
                'priceValue'    => null,
                'priceCurrency' => null
            ],
            $result
        );
    }

    public function testNormalizeObjectWithToOneRelations()
    {
        $result = $this->normalizeObject(
            $this->createProductObject()
        );

        self::assertEquals(
            [
                'id'            => 123,
                'name'          => 'product_name',
                'updatedAt'     => '2015-12-01T10:20:30Z',
                'category'      => 'category_name',
                'owner'         => 'user_name',
                'price'         => null,
                'priceValue'    => null,
                'priceCurrency' => null
            ],
            $result
        );
    }

    public function testNormalizeObjectWithNullToManyRelations()
    {
        $user = new Entity\User();
        $user->setId(123);
        $user->setName('user_name');

        $result = $this->normalizeObject($user);

        self::assertEquals(
            [
                'id'       => 123,
                'name'     => 'user_name',
                'category' => null,
                'groups'   => [],
                'products' => [],
                'owner'    => null
            ],
            $result
        );
    }

    public function testNormalizeObjectWithToManyRelations()
    {
        $result = $this->normalizeObject(
            $this->createProductObject()->getOwner()
        );

        self::assertEquals(
            [
                'id'       => 456,
                'name'     => 'user_name',
                'category' => 'owner_category_name',
                'groups'   => ['owner_group1', 'owner_group2'],
                'products' => ['product_name'],
                'owner'    => null
            ],
            $result
        );
    }
}
