<?php

namespace Oro\Bundle\UserBundle\Tests\Behat\Element;

use Behat\Mink\Element\NodeElement;
use Oro\Bundle\TestFrameworkBundle\Behat\Element\Form;

class UserRoleForm extends Form
{
    /**
     * @param string $entity Entity name e.g. Account, Business Customer, Comment etc.
     * @param string $action e.g. Create, Delete, Edit, View etc.
     * @param string $accessLevel e.g. System, None, User etc.
     */
    public function setPermission($entity, $action, $accessLevel)
    {
        $entityRows = $this->getEntityRows($entity);
        $actionRow = $this->getActionRow($entityRows, $action);
        $this->getDriver()->waitForAjax();
        $levels = $actionRow->findAll('css', 'ul.dropdown-menu-collection__list li a');
        $availableLevels = [];

        /** @var NodeElement $level */
        foreach ($levels as $level) {
            $levelCaption = strip_tags($level->getHtml());
            $availableLevels[] = $levelCaption;

            if (preg_match(sprintf('/%s/i', preg_quote($accessLevel, '\\')), $levelCaption)) {
                $level->mouseOver();
                $level->click();
                return;
            }
        }

        self::fail(sprintf(
            'Entity "%s" has no "%s" access level to choose. Available levels "%s"',
            $entity,
            $accessLevel,
            implode(',', $availableLevels)
        ));
    }

    /**
     * Checks capability permission checkbox
     *
     * @param string $name
     * @param bool $check
     */
    public function setCheckBoxPermission($name, $check = true)
    {
        $label = $this->findVisible('css', $this->selectorManipulator->addContainsSuffix('label', $name));
        $element = $label->find('css', 'input');

        if ($check) {
            $element->check();
        } else {
            $element->uncheck();
        }
    }

    /**
     * @param NodeElement[]|array $entityRows
     * @param string $action
     * @return NodeElement
     */
    protected function getActionRow(array $entityRows, $action)
    {
        foreach ($entityRows as $entityRow) {
            // Case-insensitive search for action containing given $action text
            $label = $entityRow->find(
                'xpath',
                '//span[@class="action-permissions__label"]' .
                '[contains(' .
                    'translate(text(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),' .
                     '"'.strtolower($action).'"' .
                ')]'
            );
            if ($label) {
                $label->click();

                $dropDown = $this->getPage()->findVisible('css', 'div.dropdown-menu');
                self::assertNotNull($dropDown, "Visible permission list dropdown not found for $action");

                return $dropDown;
            }
        }

        self::fail(sprintf('There is no "%s" action', $action));
    }

    /**
     * @param string $entity
     * @return NodeElement[]
     */
    protected function getEntityRows($entity)
    {
        // Find TR element which contains element div.entity-name with text $entity
        $entityTrs = $this->findAll('xpath', "//div[contains(@class,'entity-name')][text()='$entity']/ancestor::tr");
        self::assertNotCount(0, $entityTrs, sprintf('There is no "%s" entity row', $entity));

        return $entityTrs;
    }
}
