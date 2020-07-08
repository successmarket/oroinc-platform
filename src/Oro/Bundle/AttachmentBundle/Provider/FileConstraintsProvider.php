<?php

namespace Oro\Bundle\AttachmentBundle\Provider;

use Oro\Bundle\AttachmentBundle\DependencyInjection\Configuration as AttachmentConfiguration;
use Oro\Bundle\AttachmentBundle\Helper\FieldConfigHelper;
use Oro\Bundle\AttachmentBundle\Tools\MimeTypesConverter;
use Oro\Bundle\ConfigBundle\Config\ConfigManager as SystemConfigManager;

/**
 * Provides a list of constraints for uploaded file.
 */
class FileConstraintsProvider
{
    /** @var SystemConfigManager */
    private $systemConfigManager;

    /** @var AttachmentEntityConfigProviderInterface */
    private $attachmentEntityConfigProvider;

    /**
     * @param SystemConfigManager $configManager
     * @param AttachmentEntityConfigProviderInterface $attachmentEntityConfigProvider
     */
    public function __construct(
        SystemConfigManager $configManager,
        AttachmentEntityConfigProviderInterface $attachmentEntityConfigProvider
    ) {
        $this->systemConfigManager = $configManager;
        $this->attachmentEntityConfigProvider = $attachmentEntityConfigProvider;
    }

    /**
     * @return array
     */
    public function getFileMimeTypes(): array
    {
        return MimeTypesConverter::convertToArray(
            $this->systemConfigManager->get('oro_attachment.upload_file_mime_types', '')
        );
    }

    /**
     * @return array
     */
    public function getImageMimeTypes(): array
    {
        return MimeTypesConverter::convertToArray(
            $this->systemConfigManager->get('oro_attachment.upload_image_mime_types', '')
        );
    }

    /**
     * Gets file and image mime types from system config.
     *
     * @return array
     */
    public function getMimeTypes(): array
    {
        return array_unique(array_merge($this->getFileMimeTypes(), $this->getImageMimeTypes()));
    }

    /**
     * @return array
     */
    public function getMimeTypesAsChoices(): array
    {
        $mimeTypes = $this->getMimeTypes();

        return array_combine($mimeTypes, $mimeTypes);
    }

    /**
     * Gets file and image mime types from entity config.
     *
     * @param string $entityClass
     *
     * @return array
     */
    public function getAllowedMimeTypesForEntity(string $entityClass): array
    {
        $entityConfig = $this->attachmentEntityConfigProvider->getEntityConfig($entityClass);
        if ($entityConfig) {
            $mimeTypes = MimeTypesConverter::convertToArray($entityConfig->get('mimetypes'));
        }

        if (empty($mimeTypes)) {
            $mimeTypes = $this->getMimeTypes();
        }

        return $mimeTypes;
    }

    /**
     * Gets file and image mime types from entity field config.
     *
     * @param string $entityClass
     * @param string $fieldName
     *
     * @return array
     */
    public function getAllowedMimeTypesForEntityField(string $entityClass, string $fieldName): array
    {
        $entityFieldConfig = $this->attachmentEntityConfigProvider->getFieldConfig($entityClass, $fieldName);
        if ($entityFieldConfig) {
            $mimeTypes = MimeTypesConverter::convertToArray($entityFieldConfig->get('mimetypes'));
        }

        if (empty($mimeTypes)) {
            if ($entityFieldConfig && FieldConfigHelper::isImageField($entityFieldConfig->getId())) {
                $mimeTypes = $this->getImageMimeTypes();
            } else {
                $mimeTypes = $this->getFileMimeTypes();
            }
        }

        return $mimeTypes;
    }

    /**
     * Gets max allowed file size from system config.
     *
     * @return int
     */
    public function getMaxSize(): int
    {
        return $this->getMaxSizeByConfigPath('oro_attachment.maxsize');
    }

    /**
     * Gets max allowed file size from system config.
     *
     * @return int
     */
    public function getMaxSizeByConfigPath(string $maxSizeConfigPath): int
    {
        $maxFileSize = (float)$this->systemConfigManager
                ->get($maxSizeConfigPath) * AttachmentConfiguration::BYTES_MULTIPLIER;

        return (int)$maxFileSize;
    }

    /**
     * Gets max allowed file size from entity config.
     *
     * @param string $entityClass
     *
     * @return int
     */
    public function getMaxSizeForEntity(string $entityClass): int
    {
        $entityConfig = $this->attachmentEntityConfigProvider->getEntityConfig($entityClass);
        if ($entityConfig) {
            $maxFileSize = (float)$entityConfig->get('maxsize') * AttachmentConfiguration::BYTES_MULTIPLIER;
        }

        if (empty($maxFileSize)) {
            $maxFileSize = $this->getMaxSize();
        }

        return (int)$maxFileSize;
    }

    /**
     * Gets max allowed file size from entity field config.
     *
     * @param string $entityClass
     * @param string $fieldName
     *
     * @return int
     */
    public function getMaxSizeForEntityField(string $entityClass, string $fieldName): int
    {
        $entityFieldConfig = $this->attachmentEntityConfigProvider->getFieldConfig($entityClass, $fieldName);
        if ($entityFieldConfig) {
            $maxFileSize = (float)$entityFieldConfig->get('maxsize') * AttachmentConfiguration::BYTES_MULTIPLIER;
        }

        if (empty($maxFileSize)) {
            $maxFileSize = $this->getMaxSize();
        }

        return (int)$maxFileSize;
    }
}
