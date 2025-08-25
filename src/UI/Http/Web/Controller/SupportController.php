<?php

namespace App\UI\Http\Web\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Support and Documentation Controller
 */
class SupportController extends AbstractController
{
    #[Route('/support', name: 'support', methods: ['GET'])]
    public function support(): Response
    {
        return $this->render('support/index.html.twig', [
            'title' => 'Support & Help'
        ]);
    }

    #[Route('/api/docs', name: 'api_docs', methods: ['GET'])]
    public function apiDocs(): Response
    {
        return $this->render('docs/api.html.twig', [
            'title' => 'API Documentation'
        ]);
    }

    #[Route('/docs/integrations', name: 'docs_integrations', methods: ['GET'])]
    public function integrationsGuide(): Response
    {
        return $this->render('docs/integrations.html.twig', [
            'title' => 'Integration Guide'
        ]);
    }
}