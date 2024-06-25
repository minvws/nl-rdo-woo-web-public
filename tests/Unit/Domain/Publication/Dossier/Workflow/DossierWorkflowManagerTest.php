<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Publication\Dossier\Workflow;

use App\Domain\Publication\Dossier\DossierStatus;
use App\Domain\Publication\Dossier\Type\DossierTypeManager;
use App\Domain\Publication\Dossier\Type\WooDecision\WooDecision;
use App\Domain\Publication\Dossier\Workflow\DossierStatusTransition;
use App\Domain\Publication\Dossier\Workflow\DossierWorkflowException;
use App\Domain\Publication\Dossier\Workflow\DossierWorkflowManager;
use App\Service\DossierService;
use App\Service\HistoryService;
use App\Service\Inquiry\InquiryService;
use Doctrine\Common\Collections\ArrayCollection;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Component\Workflow\WorkflowInterface;

class DossierWorkflowManagerTest extends MockeryTestCase
{
    private DossierTypeManager&MockInterface $dossierTypeManager;
    private WooDecision&MockInterface $dossier;
    private WorkflowInterface&MockInterface $workflow;
    private DossierWorkflowManager $manager;
    private LoggerInterface&MockInterface $logger;
    private HistoryService&MockInterface $historyService;
    private DossierService&MockInterface $dossierService;

    public function setUp(): void
    {
        $this->logger = \Mockery::mock(LoggerInterface::class);

        $inquiryService = \Mockery::mock(InquiryService::class);
        $this->historyService = \Mockery::mock(HistoryService::class);

        $this->dossierTypeManager = \Mockery::mock(DossierTypeManager::class);

        $this->dossierService = \Mockery::mock(DossierService::class);

        $this->dossier = \Mockery::mock(WooDecision::class);
        $this->dossier->shouldReceive('getId')->andReturn(Uuid::v6());

        $this->workflow = \Mockery::mock(WorkflowInterface::class);

        $this->manager = new DossierWorkflowManager(
            $this->logger,
            $inquiryService,
            $this->historyService,
            $this->dossierTypeManager,
            $this->dossierService,
        );
    }

    public function testIsTransitionAllowedReturnsFalseWhenTheWorkflowDeniesATransition(): void
    {
        $this->dossier->shouldReceive('getStatus')->andReturn(DossierStatus::NEW);

        $this->dossierTypeManager->expects('getStatusWorkflow')->andReturn($this->workflow);

        $this->workflow->expects('can')->with($this->dossier, DossierStatusTransition::PUBLISH->value)->andReturnFalse();

        self::assertFalse(
            $this->manager->isTransitionAllowed($this->dossier, DossierStatusTransition::PUBLISH)
        );
    }

    public function testApplyTransitionThrowsExceptionForInvalidTransition(): void
    {
        $this->dossier->shouldReceive('getStatus')->andReturn(DossierStatus::NEW);

        $this->dossierTypeManager->expects('getStatusWorkflow')->andReturn($this->workflow);

        $this->workflow->expects('apply')
            ->with($this->dossier, DossierStatusTransition::PUBLISH->value)
            ->andThrow(new TransitionException($this->dossier, DossierStatusTransition::PUBLISH->value, $this->workflow, 'foo'));

        $this->logger->expects('error');

        $this->expectExceptionObject(
            DossierWorkflowException::forTransitionFailed(
                $this->dossier,
                DossierStatusTransition::PUBLISH,
                \Mockery::mock(TransitionException::class),
            )
        );
        $this->manager->applyTransition($this->dossier, DossierStatusTransition::PUBLISH);
    }

    public function testApplyTransitionUpdatesStatus(): void
    {
        $this->dossier->shouldReceive('getStatus')->andReturn(DossierStatus::CONCEPT, DossierStatus::PUBLISHED);
        $this->dossier->shouldReceive('getInquiries')->andReturn(new ArrayCollection([]));

        $this->dossierTypeManager->expects('getStatusWorkflow')->andReturn($this->workflow);

        $this->workflow->expects('apply')->with($this->dossier, DossierStatusTransition::PUBLISH->value);

        $this->logger->shouldReceive('info');

        $this->dossierService->expects('handleEntityUpdate')->with($this->dossier);
        $this->dossierService->expects('generateArchives')->with($this->dossier);

        $this->historyService->expects('addDossierEntry')->with(
            $this->dossier,
            'dossier_state_published',
            ['old' => '%concept%', 'new' => '%published%'],
        );

        $this->manager->applyTransition($this->dossier, DossierStatusTransition::PUBLISH);
    }

    public function testApplyTransitionWithoutStatusUpdateAddsNoHistory(): void
    {
        $this->dossier->shouldReceive('getStatus')->andReturn(DossierStatus::CONCEPT);

        $this->dossierTypeManager->expects('getStatusWorkflow')->andReturn($this->workflow);

        $this->workflow->expects('apply')->with($this->dossier, DossierStatusTransition::UPDATE_DOCUMENTS->value);

        $this->logger->shouldReceive('info');

        $this->dossierService->expects('handleEntityUpdate')->with($this->dossier);

        $this->manager->applyTransition($this->dossier, DossierStatusTransition::UPDATE_DOCUMENTS);
    }
}