<?php

namespace App\UI\Http\Web\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Simple Route Controller using traditional annotations
 */
class SimpleRouteController extends AbstractController
{
    /**
     * @Route("/homepage", name="homepage", methods={"GET"})
     */
    public function homepage(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    /**
     * @Route("/simple-test", name="simple_test", methods={"GET"})
     */
    public function simpleTest(): Response
    {
        return new Response('<h1>Simple Test Route Works!</h1><p>If you see this, routes are working.</p>');
    }
}