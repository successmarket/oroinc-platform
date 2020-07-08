<?php

namespace Oro\Bundle\PlatformBundle\Tests\Functional\DependencyInjection\Compiler;

use Oro\Bundle\PlatformBundle\Tests\Functional\DependencyInjection\Compiler\Stub\TestServiceLocatorInjectionInterface;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class MergeServiceLocatorsCompilerPassTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient();
    }

    /**
     * @param TestServiceLocatorInjectionInterface $service
     * @param string                               $id
     */
    private function validateInjectedServiceLocator(TestServiceLocatorInjectionInterface $service, string $id): void
    {
        try {
            $service->validateInjectedServiceLocator();
        } catch (\LogicException $e) {
            throw new \LogicException(sprintf('%s Service: %s', $e->getMessage(), $id), $e->getCode(), $e);
        }
    }

    /**
     * @param string $id
     * @param string $serviceLocatorId
     *
     * @return \LogicException
     */
    private function getServiceLocatorEqualsException(string $id, string $serviceLocatorId): \LogicException
    {
        return new \LogicException(sprintf(
            'The service locator injected in the "%s" service must be "%s".',
            $id,
            $serviceLocatorId
        ));
    }

    /**
     * @param string $id
     * @param string $serviceLocatorId
     *
     * @return \LogicException
     */
    private function getServiceLocatorNotEqualsException(string $id, string $serviceLocatorId): \LogicException
    {
        throw new \LogicException(sprintf(
            'The service locator injected in the "%s" service must not be "%s".',
            $id,
            $serviceLocatorId
        ));
    }

    public function testCompiler()
    {
        $taggedServices = [
            'oro_platform.tests.merge_service_locators.service_locator_injected_via_constructor',
            'oro_platform.tests.merge_service_locators.service_locator_injected_via_setter',
            'oro_platform.tests.merge_service_locators.service_locator_injected_via_setter_another_via_constructor',
            'oro_platform.tests.merge_service_locators.decorated',
            'oro_platform.tests.merge_service_locators.with_parent'
        ];

        $serviceLocator = self::getContainer()->get('oro_platform.tests.merge_service_locators.service_locator');
        $anotherServiceLocator = self::getContainer()
            ->get('oro_platform.tests.merge_service_locators.another_service_locator');

        foreach ($taggedServices as $id) {
            /** @var TestServiceLocatorInjectionInterface $service */
            $service = self::getContainer()->get($id);
            if ($service->getContainer() !== $serviceLocator) {
                throw $this->getServiceLocatorEqualsException(
                    $id,
                    'oro_platform.tests.merge_service_locators.service_locator'
                );
            }
            $this->validateInjectedServiceLocator($service, $id);
        }

        $id = 'oro_platform.tests.merge_service_locators.service_locator_injected_via_setter_another_via_constructor';
        $service = self::getContainer()->get($id);
        if ($service->getContainerInjectedViaConstructor() !== $anotherServiceLocator) {
            throw $this->getServiceLocatorEqualsException(
                $id,
                'oro_platform.tests.merge_service_locators.another_service_locator'
            );
        }

        $id = 'oro_platform.tests.merge_service_locators.base';
        $service = self::getContainer()->get($id);
        if ($service->getContainer() === $serviceLocator) {
            throw $this->getServiceLocatorNotEqualsException(
                $id,
                'oro_platform.tests.merge_service_locators.service_locator'
            );
        }
        $this->validateInjectedServiceLocator($service, $id);
    }
}
