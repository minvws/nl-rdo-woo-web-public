<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Publication\Dossier\Handler;

use App\Domain\Publication\Dossier\AbstractDossier;
use App\Domain\Publication\Dossier\AbstractDossierRepository;
use App\Domain\Publication\Dossier\Command\DeleteDossierCommand;
use App\Domain\Publication\Dossier\Handler\DeleteDossierHandler;
use App\Domain\Publication\Dossier\Type\DossierDeleteStrategyInterface;
use App\Domain\Publication\Dossier\Type\DossierType;
use App\Domain\Publication\Dossier\Workflow\DossierStatusTransition;
use App\Domain\Publication\Dossier\Workflow\DossierWorkflowManager;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV6;

class DeleteDossierHandlerTest extends MockeryTestCase
{
    private AbstractDossierRepository&MockInterface $dossierRepository;
    private LoggerInterface&MockInterface $logger;
    private DossierWorkflowManager&MockInterface $dossierWorkflowManager;
    private DeleteDossierHandler $handler;
    private AbstractDossier&MockInterface $dossier;
    private UuidV6 $dossierUuid;
    private DossierDeleteStrategyInterface&MockInterface $strategyA;
    private DossierDeleteStrategyInterface&MockInterface $strategyB;

    public function setUp(): void
    {
        $this->dossierRepository = \Mockery::mock(AbstractDossierRepository::class);
        $this->logger = \Mockery::mock(LoggerInterface::class);
        $this->dossierWorkflowManager = \Mockery::mock(DossierWorkflowManager::class);

        $this->strategyA = \Mockery::mock(DossierDeleteStrategyInterface::class);
        $this->strategyB = \Mockery::mock(DossierDeleteStrategyInterface::class);

        $this->dossierUuid = Uuid::v6();

        $this->dossier = \Mockery::mock(AbstractDossier::class);
        $this->dossier->shouldReceive('getId')->andReturn($this->dossierUuid);
        $this->dossier->shouldReceive('getType')->andReturn(DossierType::WOO_DECISION);

        $this->handler = new DeleteDossierHandler(
            $this->dossierRepository,
            $this->logger,
            $this->dossierWorkflowManager,
            [$this->strategyA, $this->strategyB],
        );
    }

    public function testLogsWarningWhenDossierIsNotFound(): void
    {
        $command = DeleteDossierCommand::forDossier($this->dossier);

        $this->dossierRepository->expects('find')->with($this->dossierUuid)->andReturnNull();
        $this->logger->expects('warning');

        $this->handler->__invoke($command);
    }

    public function testDeleteSuccessful(): void
    {
        $command = DeleteDossierCommand::forDossier($this->dossier);

        $this->dossierRepository->expects('find')->with($this->dossierUuid)->andReturn($this->dossier);

        $this->dossierWorkflowManager->expects('applyTransition')->with($this->dossier, DossierStatusTransition::DELETE);
        $this->strategyA->expects('delete')->with($this->dossier);
        $this->strategyB->expects('delete')->with($this->dossier);

        $this->dossierRepository->expects('remove')->with($this->dossier);

        $this->handler->__invoke($command);
    }
}