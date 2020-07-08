<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor;

use Oro\Bundle\ApiBundle\Collection\IncludedEntityCollection;
use Oro\Bundle\ApiBundle\Collection\IncludedEntityData;
use Oro\Bundle\ApiBundle\Processor\FormContext;
use Oro\Bundle\ApiBundle\Provider\ConfigProvider;
use Oro\Bundle\ApiBundle\Provider\MetadataProvider;
use Oro\Bundle\ApiBundle\Util\EntityMapper;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class FormContextTest extends \PHPUnit\Framework\TestCase
{
    /** @var FormContext */
    private $context;

    protected function setUp(): void
    {
        $configProvider = $this->createMock(ConfigProvider::class);
        $metadataProvider = $this->createMock(MetadataProvider::class);

        $this->context = new FormContextStub($configProvider, $metadataProvider);
    }

    public function testRequestData()
    {
        $requestData = [];
        $this->context->setRequestData($requestData);
        self::assertSame($requestData, $this->context->getRequestData());
    }

    public function testIncludedData()
    {
        $includedData = [];
        $this->context->setIncludedData($includedData);
        self::assertSame($includedData, $this->context->getIncludedData());
    }

    public function testIncludedEntities()
    {
        self::assertNull($this->context->getIncludedEntities());

        $includedEntities = $this->createMock(IncludedEntityCollection::class);
        $this->context->setIncludedEntities($includedEntities);
        self::assertSame($includedEntities, $this->context->getIncludedEntities());

        $this->context->setIncludedEntities();
        self::assertNull($this->context->getIncludedEntities());
    }

    public function testAdditionalEntities()
    {
        self::assertSame([], $this->context->getAdditionalEntities());

        $entity1 = new \stdClass();
        $entity2 = new \stdClass();

        $this->context->addAdditionalEntity($entity1);
        $this->context->addAdditionalEntity($entity2);
        self::assertSame([$entity1, $entity2], $this->context->getAdditionalEntities());

        $this->context->addAdditionalEntity($entity1);
        self::assertSame([$entity1, $entity2], $this->context->getAdditionalEntities());

        $this->context->removeAdditionalEntity($entity1);
        self::assertSame([$entity2], $this->context->getAdditionalEntities());
    }

    public function testEntityMapper()
    {
        $entityMapper = $this->createMock(EntityMapper::class);

        self::assertNull($this->context->getEntityMapper());

        $this->context->setEntityMapper($entityMapper);
        self::assertSame($entityMapper, $this->context->getEntityMapper());

        $this->context->setEntityMapper();
        self::assertNull($this->context->getEntityMapper());
    }

    public function testFormBuilder()
    {
        $formBuilder = $this->createMock(FormBuilderInterface::class);

        self::assertFalse($this->context->hasFormBuilder());
        self::assertNull($this->context->getFormBuilder());

        $this->context->setFormBuilder($formBuilder);
        self::assertTrue($this->context->hasFormBuilder());
        self::assertSame($formBuilder, $this->context->getFormBuilder());

        $this->context->setFormBuilder();
        self::assertFalse($this->context->hasFormBuilder());
        self::assertNull($this->context->getFormBuilder());
    }

    public function testForm()
    {
        $form = $this->createMock(FormInterface::class);

        self::assertFalse($this->context->hasForm());
        self::assertNull($this->context->getForm());

        $this->context->setForm($form);
        self::assertTrue($this->context->hasForm());
        self::assertSame($form, $this->context->getForm());

        $this->context->setForm();
        self::assertFalse($this->context->hasForm());
        self::assertNull($this->context->getForm());
    }

    public function testSkipFormValidation()
    {
        self::assertFalse($this->context->isFormValidationSkipped());

        $this->context->skipFormValidation(true);
        self::assertTrue($this->context->isFormValidationSkipped());

        $this->context->skipFormValidation(false);
        self::assertFalse($this->context->isFormValidationSkipped());
    }

    public function testGetAllEntities()
    {
        self::assertSame([], $this->context->getAllEntities());
        self::assertSame([], $this->context->getAllEntities(true));

        $entity = new \stdClass();
        $this->context->setResult($entity);
        self::assertSame([$entity], $this->context->getAllEntities());
        self::assertSame([$entity], $this->context->getAllEntities(true));

        $includedEntity = new \stdClass();
        $this->context->setIncludedEntities(new IncludedEntityCollection());
        $this->context->getIncludedEntities()
            ->add($includedEntity, \stdClass::class, 1, $this->createMock(IncludedEntityData::class));
        self::assertSame([$entity, $includedEntity], $this->context->getAllEntities());
        self::assertSame([$entity], $this->context->getAllEntities(true));
    }
}
