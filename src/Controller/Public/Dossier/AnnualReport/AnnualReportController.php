<?php

declare(strict_types=1);

namespace App\Controller\Public\Dossier\AnnualReport;

use App\Domain\Publication\Attachment\ViewModel\AttachmentViewFactory;
use App\Domain\Publication\Dossier\Type\AnnualReport\AnnualReport;
use App\Domain\Publication\Dossier\Type\AnnualReport\AnnualReportAttachment;
use App\Domain\Publication\Dossier\Type\AnnualReport\AnnualReportDocument;
use App\Domain\Publication\Dossier\Type\AnnualReport\ViewModel\AnnualReportViewFactory;
use App\Domain\Publication\MainDocument\ViewModel\MainDocumentViewFactory;
use App\Service\DossierService;
use App\Service\DownloadResponseHelper;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Annotation\Route;
use WhiteOctober\BreadcrumbsBundle\Model\Breadcrumbs;

class AnnualReportController extends AbstractController
{
    public function __construct(
        private readonly DossierService $dossierService,
        private readonly AnnualReportViewFactory $viewFactory,
        private readonly AttachmentViewFactory $attachmentViewFactory,
        private readonly MainDocumentViewFactory $mainDocumentViewFactory,
        private readonly DownloadResponseHelper $downloadHelper,
    ) {
    }

    #[Cache(maxage: 3600, public: true, mustRevalidate: true)]
    #[Route('/annual-report/{prefix}/{dossierId}', name: 'app_annualreport_detail', methods: ['GET'])]
    public function detail(
        #[MapEntity(mapping: ['prefix' => 'documentPrefix', 'dossierId' => 'dossierNr'])]
        AnnualReport $annualReport,
        #[MapEntity(expr: 'repository.findForDossierPrefixAndNr(prefix, dossierId)')]
        AnnualReportDocument $document,
        Breadcrumbs $breadcrumbs,
    ): Response {
        $breadcrumbs->addRouteItem('global.home', 'app_home');
        $breadcrumbs->addItem('public.dossiers.annual_report.breadcrumb');

        if (! $this->dossierService->isViewingAllowed($annualReport)) {
            throw $this->createNotFoundException('Annual report not found');
        }

        return $this->render('annualreport/details.html.twig', [
            'dossier' => $this->viewFactory->make($annualReport),
            'attachments' => $this->attachmentViewFactory->makeCollection($annualReport),
            'document' => $this->mainDocumentViewFactory->make($annualReport, $document),
        ]);
    }

    #[Cache(maxage: 172800, public: true, mustRevalidate: true)]
    #[Route(
        '/annual-report/{prefix}/{dossierId}/document',
        name: 'app_annualreport_document_detail',
        methods: ['GET'],
    )]
    public function documentDetail(
        #[MapEntity(mapping: ['prefix' => 'documentPrefix', 'dossierId' => 'dossierNr'])]
        AnnualReport $dossier,
        #[MapEntity(expr: 'repository.findForDossierPrefixAndNr(prefix, dossierId)')]
        AnnualReportDocument $document,
        Breadcrumbs $breadcrumbs,
    ): Response {
        if (! $this->dossierService->isViewingAllowed($dossier)) {
            throw $this->createNotFoundException('Dossier not found');
        }

        $mainDocumentViewModel = $this->mainDocumentViewFactory->make($dossier, $document);

        $breadcrumbs->addRouteItem('global.home', 'app_home');
        $breadcrumbs->addRouteItem('public.dossiers.annual_report.breadcrumb', 'app_annualreport_detail', [
            'prefix' => $dossier->getDocumentPrefix(),
            'dossierId' => $dossier->getDossierNr(),
        ]);
        $breadcrumbs->addItem($mainDocumentViewModel->name ?? '');

        return $this->render('annualreport/document.html.twig', [
            'dossier' => $this->viewFactory->make($dossier),
            'attachments' => $this->attachmentViewFactory->makeCollection($dossier),
            'document' => $mainDocumentViewModel,
        ]);
    }

    #[Cache(maxage: 172800, public: true, mustRevalidate: true)]
    #[Route(
        '/annual-report/{prefix}/{dossierId}/document/download',
        name: 'app_annualreport_document_download',
        methods: ['GET'],
    )]
    public function documentDownload(
        #[MapEntity(mapping: ['prefix' => 'documentPrefix', 'dossierId' => 'dossierNr'])]
        AnnualReport $dossier,
        #[MapEntity(expr: 'repository.findForDossierPrefixAndNr(prefix, dossierId)')]
        AnnualReportDocument $document,
    ): Response {
        if (! $this->dossierService->isViewingAllowed($dossier)) {
            throw $this->createNotFoundException('Dossier not found');
        }

        return $this->downloadHelper->getResponseForEntityWithFileInfo($document);
    }

    #[Cache(maxage: 172800, public: true, mustRevalidate: true)]
    #[Route(
        '/annual-report/{prefix}/{dossierId}/attachment/{attachmentId}',
        name: 'app_annualreport_attachment_detail',
        methods: ['GET'],
    )]
    public function attachmentDetail(
        #[MapEntity(mapping: ['prefix' => 'documentPrefix', 'dossierId' => 'dossierNr'])]
        AnnualReport $dossier,
        #[MapEntity(expr: 'repository.findForDossierPrefixAndNr(prefix, dossierId, attachmentId)')]
        AnnualReportAttachment $attachment,
        #[MapEntity(expr: 'repository.findForDossierPrefixAndNr(prefix, dossierId)')]
        AnnualReportDocument $document,
        Breadcrumbs $breadcrumbs,
    ): Response {
        if (! $this->dossierService->isViewingAllowed($dossier)) {
            throw $this->createNotFoundException('Dossier not found');
        }

        $attachmentViewModel = $this->attachmentViewFactory->make($dossier, $attachment);

        $breadcrumbs->addRouteItem('global.home', 'app_home');
        $breadcrumbs->addRouteItem('public.dossiers.annual_report.breadcrumb', 'app_annualreport_detail', [
            'prefix' => $dossier->getDocumentPrefix(),
            'dossierId' => $dossier->getDossierNr(),
        ]);
        $breadcrumbs->addItem($attachmentViewModel->name ?? '');

        return $this->render('annualreport/attachment.html.twig', [
            'dossier' => $this->viewFactory->make($dossier),
            'attachments' => $this->attachmentViewFactory->makeCollection($dossier),
            'attachment' => $attachmentViewModel,
            'document' => $this->mainDocumentViewFactory->make($dossier, $document),
        ]);
    }

    #[Cache(maxage: 172800, public: true, mustRevalidate: true)]
    #[Route(
        '/annual-report/{prefix}/{dossierId}/attachment/{attachmentId}/download',
        name: 'app_annualreport_attachment_download',
        methods: ['GET'],
    )]
    public function attachmentDownload(
        #[MapEntity(mapping: ['prefix' => 'documentPrefix', 'dossierId' => 'dossierNr'])]
        AnnualReport $dossier,
        #[MapEntity(expr: 'repository.findForDossierPrefixAndNr(prefix, dossierId, attachmentId)')]
        AnnualReportAttachment $attachment,
    ): Response {
        if (! $this->dossierService->isViewingAllowed($dossier)) {
            throw $this->createNotFoundException('Dossier not found');
        }

        return $this->downloadHelper->getResponseForEntityWithFileInfo($attachment);
    }
}