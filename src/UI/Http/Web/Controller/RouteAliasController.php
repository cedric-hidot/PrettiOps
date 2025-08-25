<?php

namespace App\UI\Http\Web\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Route Alias Controller
 * Provides route aliases for template compatibility
 */
class RouteAliasController extends AbstractController
{
    /**
     * Homepage alias - redirects to app_home
     */
    #[Route('/homepage', name: 'homepage', methods: ['GET'])]
    public function homepage(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    /**
     * Login alias - redirects to auth login
     */
    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->redirectToRoute('app_login');
    }

    /**
     * Register alias - redirects to auth register
     */
    #[Route('/register', name: 'register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->redirectToRoute('app_register');
    }

    // Pricing route removed - handled by HomeController

    // Demo route removed - handled by HomeController

    // Feature routes removed - handled by HomeController

    // Features route removed - handled by HomeController

    // Documentation route removed - handled by HomeController

    #[Route('/dashboard-alias', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }
    
    /**
     * Editor alias - redirects to app_editor
     */
    #[Route('/editor-alias', name: 'editor', methods: ['GET'])]
    public function editor(): Response
    {
        return $this->redirectToRoute('app_editor');
    }
    
    /**
     * Snippets alias - redirects to app_snippets
     */
    #[Route('/snippets-alias', name: 'snippets_index', methods: ['GET'])]
    public function snippetsIndex(): Response
    {
        return $this->redirectToRoute('app_snippets');
    }
    
    /**
     * Settings alias - redirects to app_settings
     */
    #[Route('/settings-alias', name: 'settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->redirectToRoute('app_settings');
    }
    
    /**
     * Profile alias - redirects to app_profile
     */
    #[Route('/profile-alias', name: 'profile_settings', methods: ['GET'])]
    public function profileSettings(): Response
    {
        return $this->redirectToRoute('app_profile');
    }
    
    /**
     * Analytics placeholder - redirect to dashboard for now
     */
    #[Route('/analytics-alias', name: 'analytics', methods: ['GET'])]
    public function analytics(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/logout-alias', name: 'logout', methods: ['GET'])]
    public function logout(): Response
    {
        return $this->redirectToRoute('app_logout');
    }

    /**
     * Documentation placeholders - redirect to docs for now
     */
    #[Route('/docs/getting-started', name: 'docs_getting_started', methods: ['GET'])]
    public function docsGettingStarted(): Response
    {
        return $this->redirectToRoute('app_docs');
    }

    #[Route('/docs/api', name: 'docs_api', methods: ['GET'])]
    public function docsApi(): Response
    {
        return $this->redirectToRoute('app_docs');
    }

    #[Route('/docs/guides', name: 'docs_guides', methods: ['GET'])]
    public function docsGuides(): Response
    {
        return $this->redirectToRoute('app_docs');
    }

    /**
     * Community placeholders - redirect to home for now
     */
    #[Route('/blog', name: 'blog', methods: ['GET'])]
    public function blog(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/community', name: 'community', methods: ['GET'])]
    public function community(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/support', name: 'support', methods: ['GET'])]
    public function support(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/contact', name: 'contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    /**
     * Additional footer placeholders
     */
    #[Route('/integrations', name: 'integrations', methods: ['GET'])]
    public function integrations(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/api-docs', name: 'api_docs', methods: ['GET'])]
    public function apiDocs(): Response
    {
        return $this->redirectToRoute('app_docs');
    }

    #[Route('/changelog', name: 'changelog', methods: ['GET'])]
    public function changelog(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/tutorials', name: 'tutorials', methods: ['GET'])]
    public function tutorials(): Response
    {
        return $this->redirectToRoute('app_docs');
    }

    #[Route('/about', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/careers', name: 'careers', methods: ['GET'])]
    public function careers(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/security', name: 'security', methods: ['GET'])]
    public function security(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    #[Route('/cookies', name: 'cookies', methods: ['GET'])]
    public function cookies(): Response
    {
        return $this->redirectToRoute('app_privacy');
    }

    /**
     * Legal page aliases
     */
    #[Route('/privacy-alias', name: 'privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->redirectToRoute('app_privacy');
    }

    #[Route('/terms-alias', name: 'terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->redirectToRoute('app_terms');
    }
}