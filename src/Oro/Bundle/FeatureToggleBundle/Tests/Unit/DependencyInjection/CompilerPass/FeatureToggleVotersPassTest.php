<?php

namespace Oro\Bundle\FeatureToggleBundle\Tests\Unit\DependencyInjection\CompilerPass;

use Oro\Bundle\FeatureToggleBundle\DependencyInjection\CompilerPass\FeatureToggleVotersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class FeatureToggleVotersPassTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var FeatureToggleVotersPass
     */
    protected $featureToggleVotersPass;

    protected function setUp(): void
    {
        $this->featureToggleVotersPass = new FeatureToggleVotersPass();
    }

    public function testProcess()
    {
        $voters = [
            'first_voter' => [['priority' => 20]],
            'second_voter' => [['priority' => 10]],
        ];

        $expected = [
            new Reference('second_voter'),
            new Reference('first_voter')
        ];

        /** @var Definition|\PHPUnit\Framework\MockObject\MockObject $featureChecker */
        $featureCheckerDefinition = $this->getMockBuilder(Definition::class)
            ->disableOriginalConstructor()
            ->getMock();

        $featureCheckerDefinition->expects($this->once())
            ->method('addMethodCall')
            ->with('setVoters', [$expected]);

        /** @var ContainerBuilder|\PHPUnit\Framework\MockObject\MockObject $container **/
        $container = $this->getMockBuilder(ContainerBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $container->expects($this->once())
            ->method('getDefinition')
            ->with('oro_featuretoggle.checker.feature_checker')
            ->willReturn($featureCheckerDefinition);

        $container->expects($this->once())
            ->method('findTaggedServiceIds')
            ->with('oro_featuretogle.voter')
            ->willReturn($voters);

        $this->featureToggleVotersPass->process($container);
    }
}
