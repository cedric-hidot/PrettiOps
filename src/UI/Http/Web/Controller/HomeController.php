<?php

namespace App\UI\Http\Web\Controller;

use App\Domain\User\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Repository\SnippetRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Web Home Controller
 * Handles main application web routes and renders Twig templates
 */
class HomeController extends AbstractController
{
    public function __construct(
        private readonly SnippetRepository $snippetRepository,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Homepage - redirect to dashboard if authenticated, show landing page if not
     * @Route("/", name="app_home", methods={"GET"})
     * @Route("/homepage", name="homepage", methods={"GET"})
     */
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Show landing page for anonymous users
        return $this->render('home/landing_simple.html.twig');
    }
    
    /**
     * Landing page (always accessible, no redirect)
     * @Route("/landing", name="app_landing", methods={"GET"})
     */
    public function landing(): Response
    {
        return $this->render('home/landing_simple.html.twig');
    }

    /**
     * User dashboard
     */
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(#[CurrentUser] User $user): Response
    {
        try {
            // Get user's recent snippets
            $recentSnippets = $this->snippetRepository->createQueryBuilder('s')
                ->where('s.user = :user')
                ->andWhere('s.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->orderBy('s.updatedAt', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            // Get user statistics
            $totalSnippets = $this->snippetRepository->count(['user' => $user, 'deletedAt' => null]);
            $publicSnippets = $this->snippetRepository->count([
                'user' => $user, 
                'visibility' => 'public', 
                'deletedAt' => null
            ]);

            $stats = [
                // Main stats cards
                'totalSnippets' => $totalSnippets,
                'totalViews' => 0, // TODO: Implement view tracking
                'emailSends' => 0, // TODO: Implement email tracking
                'activeLinks' => $publicSnippets,
                
                // Change metrics for stat cards
                'snippetsChange' => 0, // TODO: Calculate weekly change
                'viewsChange' => 0,
                'viewsChangePercent' => 0,
                'emailSendsChange' => 0,
                'activeLinksChange' => 0,
                
                // Keep existing stats for compatibility
                'total_snippets' => $totalSnippets,
                'public_snippets' => $publicSnippets,
                'private_snippets' => $totalSnippets - $publicSnippets,
                'monthly_limit' => $user->getMonthlySnippetLimit(),
                'monthly_used' => $user->getMonthlySnippetsUsed(),
                'can_create' => $user->canCreateSnippet()
            ];
            
            // Languages for filter dropdown
            $languages = [
                ['value' => 'javascript', 'label' => 'JavaScript'],
                ['value' => 'python', 'label' => 'Python'],
                ['value' => 'php', 'label' => 'PHP'],
                ['value' => 'java', 'label' => 'Java'],
                ['value' => 'html', 'label' => 'HTML'],
                ['value' => 'css', 'label' => 'CSS'],
            ];

            // If no snippets exist, provide empty array for template
            $snippetsForTemplate = $recentSnippets ?: [];
            
            return $this->render('app/dashboard.html.twig', [
                'user' => $user,
                'recent_snippets' => $recentSnippets,
                'snippets' => $snippetsForTemplate, // Template uses 'snippets' in some places
                'stats' => $stats,
                'languages' => $languages
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Dashboard rendering failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            $this->addFlash('error', 'Failed to load dashboard data');
            
            // Provide default stats structure to avoid template errors
            $defaultStats = [
                'totalSnippets' => 0,
                'totalViews' => 0,
                'emailSends' => 0,
                'activeLinks' => 0,
                'snippetsChange' => 0,
                'viewsChange' => 0,
                'viewsChangePercent' => 0,
                'emailSendsChange' => 0,
                'activeLinksChange' => 0,
                'total_snippets' => 0,
                'public_snippets' => 0,
                'private_snippets' => 0,
                'monthly_limit' => 10,
                'monthly_used' => 0,
                'can_create' => true
            ];
            
            return $this->render('app/dashboard.html.twig', [
                'user' => $user,
                'recent_snippets' => [],
                'snippets' => [],
                'stats' => $defaultStats,
                'languages' => []
            ]);
        }
    }

    /**
     * Snippet editor
     */
    #[Route('/editor', name: 'app_editor', methods: ['GET'])]
    #[Route('/editor/{id}', name: 'app_editor_edit', methods: ['GET'])]
    public function editor(Request $request, #[CurrentUser] User $user, ?string $id = null): Response
    {
        $snippet = null;
        
        if ($id) {
            $snippet = $this->snippetRepository->find($id);
            
            if (!$snippet) {
                $this->addFlash('error', 'Snippet not found');
                return $this->redirectToRoute('app_dashboard');
            }

            if ($snippet->getUser() !== $user) {
                $this->addFlash('error', 'Access denied');
                return $this->redirectToRoute('app_dashboard');
            }
        }

        // Check if user can create snippets (if creating new)
        if (!$snippet && !$user->canCreateSnippet()) {
            $this->addFlash('error', 'Monthly snippet limit exceeded');
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('app/editor_new.html.twig', [
            'user' => $user,
            'snippet' => $snippet,
            'is_editing' => $snippet !== null,
            'supported_languages' => [
                ['value' => 'javascript', 'label' => 'JavaScript'],
                ['value' => 'typescript', 'label' => 'TypeScript'],
                ['value' => 'python', 'label' => 'Python'],
                ['value' => 'php', 'label' => 'PHP'],
                ['value' => 'java', 'label' => 'Java'],
                ['value' => 'cpp', 'label' => 'C++'],
                ['value' => 'c', 'label' => 'C'],
                ['value' => 'rust', 'label' => 'Rust'],
                ['value' => 'go', 'label' => 'Go'],
                ['value' => 'sql', 'label' => 'SQL'],
                ['value' => 'html', 'label' => 'HTML'],
                ['value' => 'css', 'label' => 'CSS'],
                ['value' => 'json', 'label' => 'JSON'],
                ['value' => 'xml', 'label' => 'XML'],
                ['value' => 'markdown', 'label' => 'Markdown'],
                ['value' => 'shell', 'label' => 'Shell/Bash'],
                ['value' => 'yaml', 'label' => 'YAML']
            ],
            'editor_themes' => [
                ['value' => 'light', 'label' => 'Light'],
                ['value' => 'dark', 'label' => 'Dark'],
                ['value' => 'github-light', 'label' => 'GitHub Light'],
                ['value' => 'github-dark', 'label' => 'GitHub Dark'],
                ['value' => 'material-light', 'label' => 'Material Light'],
                ['value' => 'material-dark', 'label' => 'Material Dark'],
                ['value' => 'dracula', 'label' => 'Dracula'],
                ['value' => 'monokai', 'label' => 'Monokai']
            ]
        ]);
    }

    /**
     * Snippet library (user's snippets)
     */
    #[Route('/snippets', name: 'app_snippets', methods: ['GET'])]
    public function snippets(Request $request, #[CurrentUser] User $user): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $search = $request->query->get('search', '');
        $language = $request->query->get('language', '');
        $visibility = $request->query->get('visibility', '');

        try {
            $queryBuilder = $this->snippetRepository->createQueryBuilder('s')
                ->where('s.user = :user')
                ->andWhere('s.deletedAt IS NULL')
                ->setParameter('user', $user)
                ->orderBy('s.updatedAt', 'DESC')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);

            // Apply filters
            if ($search) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->like('s.title', ':search'),
                        $queryBuilder->expr()->like('s.description', ':search')
                    )
                )->setParameter('search', '%' . $search . '%');
            }

            if ($language) {
                $queryBuilder->andWhere('s.language = :language')
                    ->setParameter('language', $language);
            }

            if ($visibility) {
                $queryBuilder->andWhere('s.visibility = :visibility')
                    ->setParameter('visibility', $visibility);
            }

            $snippets = $queryBuilder->getQuery()->getResult();

            // Get total count for pagination
            $totalQuery = $this->snippetRepository->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.user = :user')
                ->andWhere('s.deletedAt IS NULL')
                ->setParameter('user', $user);

            if ($search) {
                $totalQuery->andWhere(
                    $totalQuery->expr()->orX(
                        $totalQuery->expr()->like('s.title', ':search'),
                        $totalQuery->expr()->like('s.description', ':search')
                    )
                )->setParameter('search', '%' . $search . '%');
            }

            if ($language) {
                $totalQuery->andWhere('s.language = :language')
                    ->setParameter('language', $language);
            }

            if ($visibility) {
                $totalQuery->andWhere('s.visibility = :visibility')
                    ->setParameter('visibility', $visibility);
            }

            $total = $totalQuery->getQuery()->getSingleScalarResult();

            return $this->render('app/snippets.html.twig', [
                'user' => $user,
                'snippets' => $snippets,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ],
                'filters' => [
                    'search' => $search,
                    'language' => $language,
                    'visibility' => $visibility
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Snippets page rendering failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            $this->addFlash('error', 'Failed to load snippets');
            return $this->render('app/snippets.html.twig', [
                'user' => $user,
                'snippets' => [],
                'pagination' => ['page' => 1, 'limit' => $limit, 'total' => 0, 'pages' => 0],
                'filters' => ['search' => $search, 'language' => $language, 'visibility' => $visibility]
            ]);
        }
    }

    /**
     * View a specific snippet
     */
    #[Route('/snippet/{id}', name: 'app_snippet_view', methods: ['GET'])]
    public function viewSnippet(string $id, #[CurrentUser] ?User $user): Response
    {
        try {
            $snippet = $this->snippetRepository->find($id);

            if (!$snippet) {
                throw $this->createNotFoundException('Snippet not found');
            }

            // Check access permissions
            $canAccess = false;
            
            if ($snippet->getVisibility() === 'public') {
                $canAccess = true;
            } elseif ($user && $snippet->getUser() === $user) {
                $canAccess = true;
            } elseif ($snippet->getVisibility() === 'shared') {
                // Additional sharing logic would go here
                $canAccess = false;
            }

            if (!$canAccess) {
                if (!$user) {
                    return $this->redirectToRoute('app_login');
                }
                
                $this->addFlash('error', 'Access denied');
                return $this->redirectToRoute('app_dashboard');
            }

            // Increment view count if not owner viewing
            if (!$user || $snippet->getUser() !== $user) {
                $snippet->incrementViewCount();
                $this->snippetRepository->save($snippet, true);
            }

            return $this->render('app/snippet_view.html.twig', [
                'snippet' => $snippet,
                'is_owner' => $user && $snippet->getUser() === $user,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Snippet view failed', [
                'error' => $e->getMessage(),
                'snippet_id' => $id,
                'user_id' => $user?->getId()
            ]);

            throw $this->createNotFoundException('Snippet not found or access denied');
        }
    }

    /**
     * Analytics page
     */
    #[Route('/analytics', name: 'app_analytics', methods: ['GET'])]
    public function analytics(): Response
    {
        // Get current user
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // For now, return a basic analytics page
        // TODO: Implement actual analytics data fetching
        return $this->render('app/analytics.html.twig', [
            'stats' => [
                'totalViews' => 0,
                'totalSnippets' => 0,
                'totalShares' => 0,
                'viewsThisMonth' => 0,
                'snippetsThisMonth' => 0,
                'sharesThisMonth' => 0,
            ],
            'chartData' => [],
            'recentActivity' => []
        ]);
    }

    /**
     * User settings page
     */
    #[Route('/settings', name: 'app_settings', methods: ['GET'])]
    public function settings(#[CurrentUser] User $user): Response
    {
        return $this->render('app/settings.html.twig', [
            'user' => $user,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'locales' => [
                'en' => 'English',
                'fr' => 'Français',
                'es' => 'Español',
                'de' => 'Deutsch',
                'it' => 'Italiano',
                'pt' => 'Português',
                'ja' => '日本語',
                'zh' => '中文'
            ]
        ]);
    }

    /**
     * User profile page
     */
    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function profile(#[CurrentUser] User $user): Response
    {
        try {
            // Get user statistics
            $stats = [
                'total_snippets' => $this->snippetRepository->count(['user' => $user, 'deletedAt' => null]),
                'public_snippets' => $this->snippetRepository->count([
                    'user' => $user, 
                    'visibility' => 'public', 
                    'deletedAt' => null
                ]),
                'total_views' => $this->snippetRepository->createQueryBuilder('s')
                    ->select('SUM(s.viewCount)')
                    ->where('s.user = :user')
                    ->andWhere('s.deletedAt IS NULL')
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getSingleScalarResult() ?? 0,
                'total_forks' => $this->snippetRepository->createQueryBuilder('s')
                    ->select('SUM(s.forkCount)')
                    ->where('s.user = :user')
                    ->andWhere('s.deletedAt IS NULL')
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getSingleScalarResult() ?? 0
            ];

            return $this->render('app/profile.html.twig', [
                'user' => $user,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Profile page rendering failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);

            return $this->render('app/profile.html.twig', [
                'user' => $user,
                'stats' => []
            ]);
        }
    }

    /**
     * Pricing page
     */
    #[Route('/pricing', name: 'pricing', methods: ['GET'])]
    public function pricing(): Response
    {
        return $this->render('home/pricing.html.twig');
    }
    
    /**
     * Integrations page
     */
    #[Route('/integrations', name: 'integrations', methods: ['GET'])]
    public function integrations(): Response
    {
        return $this->render('integrations/index.html.twig');
    }
    
    /**
     * API Documentation page
     */
    #[Route('/api-documentation', name: 'api_docs', methods: ['GET'])]
    public function apiDocs(): Response
    {
        return $this->render('docs/api.html.twig');
    }
    
    /**
     * Changelog page
     */
    #[Route('/changelog', name: 'changelog', methods: ['GET'])]
    public function changelog(): Response
    {
        return $this->render('docs/changelog.html.twig');
    }

    /**
     * Features page
     */
    #[Route('/features', name: 'features', methods: ['GET'])]
    public function features(): Response
    {
        return $this->render('home/features.html.twig');
    }
    
    /**
     * Help/Documentation page
     */
    #[Route('/help', name: 'app_help', methods: ['GET'])]
    #[Route('/demo', name: 'demo', methods: ['GET'])]
    #[Route('/features/editor', name: 'features_editor', methods: ['GET'])]
    #[Route('/features/sharing', name: 'features_sharing', methods: ['GET'])]
    #[Route('/features/integrations', name: 'features_integrations', methods: ['GET'])]
    #[Route('/features/collaboration', name: 'features_collaboration', methods: ['GET'])]
    #[Route('/features/security', name: 'features_security', methods: ['GET'])]
    #[Route('/documentation', name: 'documentation', methods: ['GET'])]
    public function help(): Response
    {
        return $this->render('help/index.html.twig');
    }

    /**
     * Demo page with all pages listed for testing
     */
    #[Route('/frontend-test', name: 'frontend_test', methods: ['GET'])]
    public function frontendTest(): Response
    {
        // Get all available routes for testing
        $testRoutes = [
            'Landing Pages' => [
                ['name' => 'Homepage (Landing)', 'route' => 'app_home', 'params' => []],
                ['name' => 'Simple Landing', 'route' => 'homepage', 'params' => []],
                ['name' => 'Pricing Page', 'route' => 'pricing', 'params' => []],
                ['name' => 'Demo Page', 'route' => 'demo', 'params' => []],
                ['name' => 'Features Overview', 'route' => 'features', 'params' => []],
            ],
            'Authentication' => [
                ['name' => 'Login Page', 'route' => 'app_login', 'params' => []],
                ['name' => 'Register Page', 'route' => 'app_register', 'params' => []],
            ],
            'App Pages (Require Auth)' => [
                ['name' => 'Dashboard', 'route' => 'app_dashboard', 'params' => []],
                ['name' => 'Snippet Editor', 'route' => 'app_editor', 'params' => []],
                ['name' => 'My Snippets', 'route' => 'app_snippets', 'params' => []],
                ['name' => 'Settings', 'route' => 'app_settings', 'params' => []],
                ['name' => 'Profile', 'route' => 'app_profile', 'params' => []],
            ],
            'Static Pages' => [
                ['name' => 'Help & Documentation', 'route' => 'app_help', 'params' => []],
                ['name' => 'Privacy Policy', 'route' => 'app_privacy', 'params' => []],
                ['name' => 'Terms of Service', 'route' => 'app_terms', 'params' => []],
                ['name' => 'API Documentation', 'route' => 'app_docs', 'params' => []],
            ]
        ];

        return $this->render('frontend_test.html.twig', [
            'test_routes' => $testRoutes,
            'current_url' => $this->generateUrl('frontend_test')
        ]);
    }

    /**
     * Privacy policy page
     */
    #[Route('/privacy', name: 'app_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }

    /**
     * Terms of service page
     */
    #[Route('/terms', name: 'app_terms', methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('legal/terms.html.twig');
    }

    /**
     * API documentation page
     */
    #[Route('/docs', name: 'app_docs', methods: ['GET'])]
    public function docs(): Response
    {
        return $this->render('docs/index.html.twig');
    }

    /**
     * Health check endpoint
     */
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(): Response
    {
        try {
            // Check database connection
            $this->userRepository->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->getQuery()
                ->getSingleScalarResult();

            return $this->json([
                'status' => 'healthy',
                'timestamp' => (new \DateTimeImmutable())->format('c'),
                'version' => '1.0.0'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'status' => 'unhealthy',
                'error' => 'Database connection failed',
                'timestamp' => (new \DateTimeImmutable())->format('c')
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Robots.txt
     */
    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $content = <<<EOF
User-agent: *
Allow: /
Disallow: /api/
Disallow: /admin/
Disallow: /_profiler/

Sitemap: {$this->getParameter('app.url')}/sitemap.xml
EOF;

        return new Response($content, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Sitemap.xml
     */
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(): Response
    {
        $baseUrl = $this->getParameter('app.url') ?? 'https://prettiops.com';
        
        $urls = [
            ['loc' => $baseUrl, 'priority' => '1.0'],
            ['loc' => $baseUrl . '/help', 'priority' => '0.8'],
            ['loc' => $baseUrl . '/docs', 'priority' => '0.8'],
            ['loc' => $baseUrl . '/privacy', 'priority' => '0.5'],
            ['loc' => $baseUrl . '/terms', 'priority' => '0.5'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . "\n";
            $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        
        $xml .= '</urlset>';

        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }
    
    /**
     * Test dropdown functionality
     */
    #[Route('/test-dropdown', name: 'test_dropdown', methods: ['GET'])]
    public function testDropdown(): Response
    {
        return $this->render('test_dropdown.html.twig');
    }

}