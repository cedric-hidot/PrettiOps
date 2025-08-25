<?php

namespace App\UI\Http\Web\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Integrations Controller
 * Handles integration pages for GitHub, Jira, IDE, etc.
 */
#[Route('/integrations', name: 'app_integrations')]
class IntegrationsController extends AbstractController
{
    /**
     * Integrations overview page
     */
    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('integrations/index.html.twig', [
            'title' => 'Integrations'
        ]);
    }

    /**
     * GitHub integration page
     */
    #[Route('/github', name: '_github', methods: ['GET'])]
    public function github(): Response
    {
        return $this->render('integrations/github.html.twig', [
            'title' => 'GitHub Integration'
        ]);
    }

    /**
     * Jira integration page
     */
    #[Route('/jira', name: '_jira', methods: ['GET'])]
    public function jira(): Response
    {
        return $this->render('integrations/jira.html.twig', [
            'title' => 'Jira Integration'
        ]);
    }

    /**
     * IDE integration page
     */
    #[Route('/ide', name: '_ide', methods: ['GET'])]
    public function ide(): Response
    {
        return $this->render('integrations/ide.html.twig', [
            'title' => 'IDE Integration'
        ]);
    }
}