<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Domain\Search\Query\SearchParametersFactory;
use App\Service\Search\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Annotation\Route;
use WhiteOctober\BreadcrumbsBundle\Model\Breadcrumbs;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly SearchParametersFactory $searchParametersFactory,
    ) {
    }

    #[Cache(maxage: 600, public: true, mustRevalidate: true)]
    #[Route('/', name: 'app_home')]
    public function index(Request $request, Breadcrumbs $breadcrumbs): Response
    {
        $breadcrumbs->addItem('global.home');

        // If we have a POST request, we have a search query in the body. Redirect to GET request
        // so we have the q in the query string.
        if ($request->isMethod('POST')) {
            $q = strval($request->request->get('q'));

            // Redirect to GET request, so we have the q in the query string.
            return $this->redirect($this->generateUrl('app_home', ['q' => $q]));
        }

        // From here we always have a 'q' from the query string
        if ($request->query->has('q')) {
            $q = strval($request->query->get('q'));

            return new RedirectResponse($this->generateUrl('app_search', ['q' => $q]));
        }

        $searchParameters = $this->searchParametersFactory->createDefault();
        $facetResult = $this->searchService->searchFacets($searchParameters);

        return $this->render('home/index.html.twig', [
            'facets' => $facetResult,
        ]);
    }
}
