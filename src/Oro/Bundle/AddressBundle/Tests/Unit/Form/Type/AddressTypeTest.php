<?php

namespace Oro\Bundle\AddressBundle\Tests\Unit\Form\Type;

use Oro\Bundle\AddressBundle\Entity\Address;
use Oro\Bundle\AddressBundle\Form\EventListener\AddressIdentifierSubscriber;
use Oro\Bundle\AddressBundle\Form\Type\AddressType;
use Oro\Component\Testing\Unit\AddressFormExtensionTestCase;
use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Component\Testing\Unit\Form\EventListener\Stub\AddressCountryAndRegionSubscriberStub;

class AddressTypeTest extends AddressFormExtensionTestCase
{
    use EntityTrait;

    /**
     * @var AddressType
     */
    private $type;

    protected function setUp(): void
    {
        $this->type = new AddressType(
            new AddressCountryAndRegionSubscriberStub(),
            new AddressIdentifierSubscriber()
        );

        parent::setUp();
    }

    /**
     * Test that ID from the entity passed to mapped=>false field ID of the form by AddressIdentifierSubscriber
     */
    public function testEntityIdPassedToForm()
    {
        /** @var Address $address */
        $address = $this->getEntity(Address::class, ['id' => 5]);

        $form = $this->factory->create(AddressType::class, $address);
        $this->assertEquals($address->getId(), $form->get('id')->getData());

        $form->submit(['id' => 10]);

        // Should not change entity ID
        $this->assertNotEquals($address->getId(), $form->get('id')->getData());
    }

    /**
     * @param mixed $defaultData
     * @param array $submittedData
     * @param mixed $expectedData
     * @dataProvider submitProvider
     */
    public function testSubmit($defaultData, $submittedData, $expectedData)
    {
        $form = $this->factory->create(AddressType::class, $defaultData);

        $form->submit($submittedData);

        $this->assertTrue($form->isValid(), $form->getErrors(true));
        $this->assertTrue($form->isSynchronized());

        $this->assertEquals($expectedData, $form->getData());
    }

    /**
     * @return array
     */
    public function submitProvider()
    {
        list($country, $region) = $this->getValidCountryAndRegion();

        $filledAddress = new Address();
        $filledAddress
            ->setLabel('address label_stripped')
            ->setNamePrefix('prefix_stripped')
            ->setFirstName('first name_stripped')
            ->setMiddleName('middle name_stripped')
            ->setLastName('last name_stripped')
            ->setNameSuffix('name suffix_stripped')
            ->setOrganization('organization name_stripped')
            ->setCountry($country)
            ->setStreet('street name_stripped')
            ->setStreet2('street2 name_stripped')
            ->setCity('city name_stripped')
            ->setRegion($region)
            ->setRegionText('Alaska')
            ->setPostalCode('123456_stripped');

        // ID submitted to the form should be ignored due to mapped=>false for ID field
        $expectedExistingAddress = clone $filledAddress;

        return [
            'new entity' => [
                'defaultData' => new Address(),
                'submittedData' => [
                    'id' => null,
                    'label' => 'address label',
                    'namePrefix' => 'prefix',
                    'firstName' => 'first name',
                    'middleName' => 'middle name',
                    'lastName' => 'last name',
                    'nameSuffix' => 'name suffix',
                    'organization' => 'organization name',
                    'country' => self::COUNTRY_WITH_REGION,
                    'street' => 'street name',
                    'street2' => 'street2 name',
                    'city' => 'city name',
                    'region' => self::REGION_WITH_COUNTRY,
                    'region_text' => 'Alaska',
                    'postalCode' => '123456'
                ],
                'expectedData' => $filledAddress,
            ],
            'existing entity' => [
                'defaultData' => clone $filledAddress,
                'submittedData' => [
                    'id' => 5,
                    'label' => 'address label',
                    'namePrefix' => 'prefix',
                    'firstName' => 'first name',
                    'middleName' => 'middle name',
                    'lastName' => 'last name',
                    'nameSuffix' => 'name suffix',
                    'organization' => 'organization name',
                    'country' => self::COUNTRY_WITH_REGION,
                    'street' => 'street name',
                    'street2' => 'street2 name',
                    'city' => 'city name',
                    'region' => self::REGION_WITH_COUNTRY,
                    'region_text' => 'Alaska',
                    'postalCode' => '123456'
                ],
                'expectedData' => $expectedExistingAddress,
            ]
        ];
    }
}
