<?php

namespace Oro\Bundle\NavigationBundle\Tests\Functional\Command;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class JsRoutingDumpCommandTest extends WebTestCase
{
    /** @var string */
    private $filenamePrefix;

    protected function setUp()
    {
        $this->initClient();
        $this->filenamePrefix = $this->getContainer()->getParameter('oro_navigation.js_routing_filename_prefix');
    }

    public function testExecute(): void
    {
        $result = $this->runCommand('fos:js-routing:dump', ['-vvv']);

        $this->assertNotEmpty($result);
        $this->assertContains($this->getEndPath($this->getFilename(), 'json'), $result);
    }

    public function testExecuteWithJsFormat(): void
    {
        $result = $this->runCommand('fos:js-routing:dump', ['-vvv', '--format=js']);

        $this->assertNotEmpty($result);
        $this->assertContains($this->getEndPath($this->getFilename(), 'js'), $result);
    }

    public function testExecuteWithCustomTarget(): void
    {
        $projectDir = $this->getContainer()
            ->getParameter('kernel.project_dir');

        $endPath = $this->getEndPath('custom_routes', 'json');

        $result = $this->runCommand('fos:js-routing:dump', ['-vvv', sprintf('--target=%s%s', $projectDir, $endPath)]);

        $this->assertNotEmpty($result);
        $this->assertContains($endPath, $result);
    }

    /**
     * @param string $filename
     * @param string $format
     * @return string
     */
    private function getEndPath(string $filename, string $format): string
    {
        return implode(DIRECTORY_SEPARATOR, ['', 'public', 'media', 'js', $filename . '.' . $format]);
    }

    /**
     * @return string
     */
    private function getFilename(): string
    {
        return $this->filenamePrefix . 'routes';
    }
}
