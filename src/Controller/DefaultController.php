<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home.html.twig');
    }

    #[Route('/auth', name: 'auth_page')]
    public function authPage(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }
        return $this->render('auth.html.twig');
    }

    #[Route('/event/{id}', name: 'event_detail', requirements: ['id' => '\d+'])]
    public function eventDetail(int $id): Response
    {
        return $this->render('event/detail.html.twig', ['eventId' => $id]);
    }
}
