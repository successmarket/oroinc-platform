<?php

namespace Oro\Bundle\ReportBundle\Tests\Functional\Command;

use Oro\Bundle\ReportBundle\Command\CalendarDateCommand;
use Oro\Bundle\ReportBundle\Entity\CalendarDate;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class CalendarDateCommandTest extends WebTestCase
{
    const DATE_FORMAT = 'Y-m-d';

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->initClient([], $this->generateBasicAuthHeader());
        $this->loadFixtures([]);
    }

    public function testGenerateDates()
    {
        $doctrineHelper = $this->getContainer()->get('oro_entity.doctrine_helper');
        $repository = $doctrineHelper->getEntityRepository(CalendarDate::class);
        $manager = $doctrineHelper->getEntityManager(CalendarDate::class);

        // Empty CalendarDate table.
        $results = $repository->findAll();
        foreach ($results as $result) {
            $manager->remove($result);
        }
        $manager->flush();

        $this->runCommand(CalendarDateCommand::getDefaultName());
        // Check that another command execution does not add duplicate dates
        $this->runCommand(CalendarDateCommand::getDefaultName());

        $results = $repository->findAll();
        $this->assertNotEmpty($results);
        $this->assertCalendarDates($results);
    }

    /**
     * @param array $calendarDates
     */
    protected function assertCalendarDates(array $calendarDates)
    {
        $generatedDates = [];
        foreach ($calendarDates as $calendarDate) {
            $formattedDate = $calendarDate->getDate()->format(self::DATE_FORMAT);
            // Assert that no duplicate dates present
            $this->assertArrayNotHasKey($formattedDate, $generatedDates);

            /** @var CalendarDate $calendarDate */
            $generatedDates[$formattedDate] = $calendarDate->getDate();
        }

        $requiredDates = $this->getGeneratedDates();
        foreach ($requiredDates as $date) {
            /** @var \DateTime $date */
            $this->assertArrayHasKey($date->format(self::DATE_FORMAT), $generatedDates);
        }
    }

    /**
     * @return array
     */
    private function getGeneratedDates()
    {
        $dates = [];
        $period = new \DatePeriod(
            new \DateTime('first day of this year'),
            new \DateInterval('P1D'),
            new \DateTime('tomorrow')
        );
        foreach ($period as $date) {
            $dates[] = $date;
        }

        return $dates;
    }
}
