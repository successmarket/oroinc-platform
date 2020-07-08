<?php

namespace Oro\Bundle\ApiBundle\Tests\Functional\Environment\Processor;

use Oro\Bundle\ApiBundle\Processor\Get\GetContext;
use Oro\Bundle\ApiBundle\Tests\Functional\Environment\Entity\TestMagazine;
use Oro\Bundle\ApiBundle\Tests\Functional\Environment\Model\TestArticleModel1;
use Oro\Bundle\ApiBundle\Tests\Functional\Environment\Model\TestMagazineModel1;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

class LoadTestMagazineModel1 implements ProcessorInterface
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    /**
     * @param DoctrineHelper $doctrineHelper
     */
    public function __construct(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var GetContext $context */

        if ($context->hasResult()) {
            // data already loaded
            return;
        }

        $magazineModel = null;
        $em = $this->doctrineHelper->getEntityManagerForClass(TestMagazine::class);
        /** @var TestMagazine|null $magazineEntity */
        $magazineEntity = $em->find(TestMagazine::class, $context->getId());
        if (null !== $magazineEntity) {
            $magazineModel = new TestMagazineModel1();
            $magazineModel->setId($magazineEntity->getId());
            $magazineModel->setName($magazineEntity->getName());
            foreach ($magazineEntity->getArticles() as $articleEntity) {
                $articleModel = new TestArticleModel1();
                $articleModel->setId($articleEntity->getId());
                $articleModel->setHeadline($articleEntity->getHeadline());
                $articleModel->setBody($articleEntity->getBody());
                $magazineModel->addArticle($articleModel);
            }
            $bestArticleEntity = $magazineEntity->getBestArticle();
            if (null !== $bestArticleEntity) {
                $bestArticleModel = new TestArticleModel1();
                $bestArticleModel->setId($bestArticleEntity->getId());
                $bestArticleModel->setHeadline($bestArticleEntity->getHeadline());
                $bestArticleModel->setBody($bestArticleEntity->getBody());
                $magazineModel->setBestArticle($bestArticleModel);
            }
        }

        $context->setResult($magazineModel);
    }
}
