<?php

namespace Oro\Component\Testing\Command\Assert;

use Oro\Component\Testing\Command\CommandOutputNormalizer;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * Checks if the command produced a warning (output should contain '[WARNING]' indicator, optionally - a specific
 * warning message text).
 */
class CommandProducedWarning extends Constraint
{
    /** @var string|null */
    private $expectedWarningMessage;

    /** @var array */
    private $errors = [];

    /**
     * @param string|null $expectedWarningMessage
     */
    public function __construct(?string $expectedWarningMessage)
    {
        $this->expectedWarningMessage = $expectedWarningMessage;
    }

    protected function matches($commandTester): bool
    {
        /** @var \Symfony\Component\Console\Tester\CommandTester $commandTester */
        $output = CommandOutputNormalizer::toSingleLine($commandTester);
        if (false === \strpos($output, '[WARNING]')) {
            $this->errors[] = 'The console command should display a warning message if there were any warnings.';
        }
        if (null !== $this->expectedWarningMessage && false === \strpos($output, $this->expectedWarningMessage)) {
            $this->errors[] = \sprintf(
                'The console command should display the warning message "%s".',
                $this->expectedWarningMessage
            );
        }
        return 0 === count($this->errors);
    }

    protected function failureDescription($commandTester): string
    {
        /** @var \Symfony\Component\Console\Tester\CommandTester $commandTester */
        return "Command produced a warning:\n" . $commandTester->getDisplay();
    }

    public function toString(): string
    {
        return 'command produced a warning';
    }
}
