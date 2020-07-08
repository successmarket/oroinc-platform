<?php

namespace Oro\Bundle\ApiBundle\Tests\Unit\Processor\GetConfig;

use Oro\Bundle\ApiBundle\Processor\GetConfig\ExcludeCustomFieldsByDefault;

class ExcludeCustomFieldsByDefaultTest extends ConfigProcessorTestCase
{
    /** @var ExcludeCustomFieldsByDefault */
    private $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new ExcludeCustomFieldsByDefault();
    }

    public function testShouldSetCustomFieldsToExclusionPolicyIfExclusionPolicyIsNotSetYet()
    {
        $this->context->setResult($this->createConfigObject([]));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'custom_fields'
            ],
            $this->context->getResult()
        );
    }

    public function testProcessWhenExclusionPolicyIsAlreadySetToAll()
    {
        $this->context->setResult($this->createConfigObject(['exclusion_policy' => 'all']));
        $this->processor->process($this->context);

        $this->assertConfig(
            [
                'exclusion_policy' => 'all'
            ],
            $this->context->getResult()
        );
    }

    public function testProcessWhenExclusionPolicyIsAlreadySetToNone()
    {
        $this->context->setResult($this->createConfigObject(['exclusion_policy' => 'none']));
        $this->processor->process($this->context);

        $this->assertConfig(
            [],
            $this->context->getResult()
        );
    }
}
