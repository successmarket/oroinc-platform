<?php

namespace Oro\Bundle\EntityConfigBundle\Form\Handler;

use Oro\Bundle\EntityConfigBundle\Config\ConfigHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Entity\EntityConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityExtendBundle\Form\Type\FieldType;
use Oro\Bundle\EntityExtendBundle\Form\Util\FieldSessionStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Manage config fields
 */
class CreateUpdateConfigFieldHandler
{
    /** @var ConfigHelperHandler */
    private $configHelperHandler;

    /** @var FieldSessionStorage */
    private $sessionStorage;

    /** @var ConfigManager */
    protected $configManager;

    /** @var ConfigHelper */
    protected $configHelper;

    /**
     * @param ConfigHelperHandler $configHelperHandler
     * @param ConfigManager $configManager
     * @param ConfigHelper $configHelper
     * @param FieldSessionStorage $sessionStorage
     */
    public function __construct(
        ConfigHelperHandler $configHelperHandler,
        ConfigManager $configManager,
        ConfigHelper $configHelper,
        FieldSessionStorage $sessionStorage
    ) {
        $this->configHelperHandler = $configHelperHandler;
        $this->configManager = $configManager;
        $this->configHelper = $configHelper;
        $this->sessionStorage = $sessionStorage;
    }

    /**
     * @param Request $request
     * @param FieldConfigModel $fieldConfigModel
     * @param string $formAction
     * @return array|RedirectResponse
     */
    public function handleCreate(Request $request, FieldConfigModel $fieldConfigModel, $formAction)
    {
        $entityConfigModel = $fieldConfigModel->getEntity();
        $form = $this->configHelperHandler->createFirstStepFieldForm($fieldConfigModel);

        if ($this->configHelperHandler->isFormValidAfterSubmit($request, $form)) {
            $fieldName = $fieldConfigModel->getFieldName();
            $originalFieldNames = $form->getConfig()->getAttribute(FieldType::ORIGINAL_FIELD_NAMES_ATTRIBUTE);
            if (isset($originalFieldNames[$fieldName])) {
                $fieldName = $originalFieldNames[$fieldName];
            }

            $this->sessionStorage->saveFieldInfo($entityConfigModel, $fieldName, $fieldConfigModel->getType());

            return $this->configHelperHandler->redirect($entityConfigModel);
        }

        return [
            'form' => $form->createView(),
            'formAction' => $formAction,
            'entity_id' => $entityConfigModel->getId(),
            'entity_config' => $this->configHelper->getEntityConfig($entityConfigModel, 'entity'),
        ];
    }

    /**
     * @param Request $request
     * @param EntityConfigModel $entityConfigModel
     * @param string $createActionRedirectUrl
     * @param string $formAction
     * @param string $successMessage
     * @param array $additionalFieldOptions
     * @return array|RedirectResponse
     */
    public function handleFieldSave(
        Request $request,
        EntityConfigModel $entityConfigModel,
        $createActionRedirectUrl,
        $formAction,
        $successMessage,
        array $additionalFieldOptions = []
    ) {
        if (!$this->sessionStorage->hasFieldInfo($entityConfigModel)) {
            return $this->configHelperHandler->redirect($createActionRedirectUrl);
        }

        list($fieldName, $fieldType) = $this->sessionStorage->getFieldInfo($entityConfigModel);

        $extendEntityConfig = $this->configHelper->getEntityConfig($entityConfigModel, 'extend');
        $newFieldModel = $this->createFieldModel($fieldName, $fieldType, $extendEntityConfig, $additionalFieldOptions);

        $form = $this->configHelperHandler->createSecondStepFieldForm($newFieldModel);

        if ($this->configHelperHandler->isFormValidAfterSubmit($request, $form)) {
            $extendEntityConfig->set('upgradeable', true);

            //persist data inside the form
            $this->configManager->persist($extendEntityConfig);
            $this->configManager->flush();

            return $this
                ->configHelperHandler->showClearCacheMessage()
                ->showSuccessMessageAndRedirect($newFieldModel, $successMessage);
        }

        return $this->configHelperHandler->constructConfigResponse($newFieldModel, $form, $formAction);
    }

    /**
     * @param string $fieldName
     * @param string $fieldType
     * @param ConfigInterface $extendEntityConfig
     * @param array $additionalFieldOptions
     *
     * @return FieldConfigModel
     */
    public function createFieldModel(
        $fieldName,
        $fieldType,
        ConfigInterface $extendEntityConfig,
        array $additionalFieldOptions = []
    ): FieldConfigModel {
        list($fieldType, $fieldOptions) = $this->configHelper->createFieldOptions(
            $extendEntityConfig,
            $fieldType,
            $additionalFieldOptions
        );

        return $this->createAndUpdateFieldModel(
            $extendEntityConfig->getId()->getClassName(),
            $fieldName,
            $fieldType,
            $fieldOptions
        );
    }

    /**
     * @param string $entityClassName
     * @param string $fieldName
     * @param string $fieldType
     * @param array $fieldOptions
     * @return FieldConfigModel
     */
    protected function createAndUpdateFieldModel(
        string $entityClassName,
        string $fieldName,
        string $fieldType,
        array $fieldOptions
    ): FieldConfigModel {
        $newFieldModel = $this->configManager->createConfigFieldModel($entityClassName, $fieldName, $fieldType);

        $this->configHelper->updateFieldConfigs($newFieldModel, $fieldOptions);

        return $newFieldModel;
    }
}
