<?php

declare(strict_types=1);

namespace App\Controller\Admin\Dossier\WooDecision;

use App\Domain\Publication\Dossier\Step\StepActionHelper;
use App\Domain\Publication\Dossier\Step\StepName;
use App\Domain\Publication\Dossier\Type\WooDecision\WooDecision;
use App\Form\Dossier\WooDecision\InventoryType;
use App\Repository\DocumentRepository;
use App\Service\DossierWizard\DossierWizardHelper;
use App\ValueObject\InventoryStatus;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use WhiteOctober\BreadcrumbsBundle\Model\Breadcrumbs;

class DocumentsEditStepController extends AbstractController
{
    public function __construct(
        private readonly DossierWizardHelper $wizardHelper,
        private readonly StepActionHelper $stepHelper,
        private readonly DocumentRepository $documentRepository,
        private readonly DocumentsStepHelper $documentsHelper,
    ) {
    }

    #[Route(
        path: '/balie/dossier/woodecision/documents/edit/{prefix}/{dossierId}',
        name: 'app_admin_dossier_woodecision_documents_edit',
        methods: ['GET', 'POST'],
    )]
    #[IsGranted('AuthMatrix.document.update', subject: 'dossier')]
    public function edit(
        #[MapEntity(mapping: ['prefix' => 'documentPrefix', 'dossierId' => 'dossierNr'])] WooDecision $dossier,
        Request $request,
        Breadcrumbs $breadcrumbs
    ): Response {
        $wizardStatus = $this->stepHelper->getWizardStatus($dossier, StepName::DOCUMENTS);
        if (! $wizardStatus->isCurrentStepAccessibleInEditMode()) {
            return $this->stepHelper->redirectToDossier($dossier);
        }

        $query = $this->documentRepository->getDossierDocumentsQueryBuilder($dossier);

        $pagination = $this->stepHelper->getPaginator(
            $query,
            $request->query->getInt('page', 1),
        );

        $this->stepHelper->addDossierToBreadcrumbs($breadcrumbs, $dossier, 'workflow_step_documents');

        $dataPath = null;
        if ($dossier->getProcessRun()?->isNotFinal()) {
            $dataPath = 'app_admin_dossier_woodecision_documents_edit_inventory_status';
        }

        return $this->render('admin/dossier/woo-decision/documents/edit.html.twig', [
            'breadcrumbs' => $breadcrumbs,
            'dossier' => $dossier,
            'workflowStatus' => $wizardStatus,
            'pagination' => $pagination,
            'dataPath' => $dataPath,
        ]);
    }

    #[Route(
        path: '/balie/dossier/woodecision/documents/edit/inventory-status/{prefix}/{dossierId}',
        name: 'app_admin_dossier_woodecision_documents_edit_inventory_status',
        methods: ['GET'],
    )]
    #[IsGranted('AuthMatrix.dossier.update', subject: 'dossier')]
    public function inventoryProcess(
        #[MapEntity(mapping: ['prefix' => 'documentPrefix', 'dossierId' => 'dossierNr'])] WooDecision $dossier,
    ): Response {
        $wizardStatus = $this->stepHelper->getWizardStatus($dossier, StepName::DOCUMENTS);
        if (! $wizardStatus->isCurrentStepAccessibleInEditMode()) {
            throw $this->createAccessDeniedException();
        }

        return $this->documentsHelper->getInventoryProcessResponse($dossier);
    }

    #[Route(
        path: '/balie/dossier/woodecision/documents/edit/replace-inventory/{prefix}/{dossierId}',
        name: 'app_admin_dossier_woodecision_documents_edit_replace_inventory',
        methods: ['GET', 'POST'],
    )]
    #[IsGranted('AuthMatrix.dossier.update', subject: 'dossier')]
    public function replaceInventory(
        #[MapEntity(mapping: ['prefix' => 'documentPrefix', 'dossierId' => 'dossierNr'])] WooDecision $dossier,
        Request $request,
        Breadcrumbs $breadcrumbs
    ): Response {
        $wizardStatus = $this->stepHelper->getWizardStatus($dossier, StepName::DOCUMENTS);
        if (! $wizardStatus->isCurrentStepAccessibleInEditMode()) {
            return $this->stepHelper->redirectToDossier($dossier);
        }

        $form = $this->createForm(InventoryType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->wizardHelper->updateInventory($dossier, $form);
        }

        if (intval($request->get('confirm')) === 1) {
            $this->wizardHelper->confirmInventoryUpdate($dossier);
        }

        if (intval($request->get('reject')) === 1) {
            $this->wizardHelper->rejectInventoryUpdate($dossier);

            return $this->redirectToRoute(
                'app_admin_dossier_woodecision_documents_edit',
                ['prefix' => $dossier->getDocumentPrefix(), 'dossierId' => $dossier->getDossierNr()]
            );
        }

        $processRun = $this->documentsHelper->mapProcessRunToForm($dossier, $form);

        $dataPath = null;
        if ($processRun?->isNotFinal()) {
            $dataPath = 'app_admin_dossier_woodecision_documents_edit_inventory_status';
        }

        $this->stepHelper->addDossierToBreadcrumbs($breadcrumbs, $dossier);
        $breadcrumbs->addRouteItem(
            'workflow_step_documents',
            'app_admin_dossier_woodecision_documents_edit',
            ['prefix' => $dossier->getDocumentPrefix(), 'dossierId' => $dossier->getDossierNr()]
        );
        $breadcrumbs->addItem('Replace inventory');

        return $this->render('admin/dossier/woo-decision/documents/replace-inventory.html.twig', [
            'breadcrumbs' => $breadcrumbs,
            'dossier' => $dossier,
            'processRun' => $processRun,
            'workflowStatus' => $wizardStatus,
            'inventoryForm' => $form,
            'inventoryStatus' => new InventoryStatus($dossier),
            'dataPath' => $dataPath,
        ]);
    }

    #[Route(
        path: '/balie/dossier/woodecision/documents/edit/search/{prefix}/{dossierId}',
        name: 'app_admin_dossier_woodecision_documents_edit_search',
        methods: ['POST'],
    )]
    #[IsGranted('AuthMatrix.dossier.read', subject: 'dossier')]
    public function documentSearch(
        #[MapEntity(mapping: ['prefix' => 'documentPrefix', 'dossierId' => 'dossierNr'])] WooDecision $dossier,
        Request $request
    ): Response {
        $wizardStatus = $this->stepHelper->getWizardStatus($dossier, StepName::DOCUMENTS);
        if (! $wizardStatus->isCurrentStepAccessibleInEditMode()) {
            throw $this->createAccessDeniedException();
        }

        $searchTerm = urldecode(strval($request->getPayload()->get('q', '')));

        $documents = $this->documentRepository->findForDossierBySearchTerm($dossier, $searchTerm, 4);

        $ret = [
            'results' => json_encode(
                $this->renderView(
                    'admin/dossier/search.html.twig',
                    [
                        'documents' => $documents,
                        'searchTerm' => $searchTerm,
                    ],
                ),
                JSON_THROW_ON_ERROR,
            ),
        ];

        return new JsonResponse($ret);
    }
}
