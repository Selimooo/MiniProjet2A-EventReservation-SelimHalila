<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AdminWebController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepo,
        private ReservationRepository $reservationRepo,
        private EntityManagerInterface $em
    ) {}

    #[Route('/admin/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/login.html.twig', [
            'error' => $authUtils->getLastAuthenticationError(),
            'lastUsername' => $authUtils->getLastUsername(),
        ]);
    }

    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $events = $this->eventRepo->findAll();
        $totalReservations = count($this->reservationRepo->findAll());

        return $this->render('admin/dashboard.html.twig', [
            'events' => $events,
            'totalReservations' => $totalReservations,
        ]);
    }

    #[Route('/admin/events/new', name: 'admin_event_new', methods: ['GET', 'POST'])]
    public function newEvent(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $event = new Event();
            $event->setTitle($request->request->get('title'));
            $event->setDescription($request->request->get('description'));
            $event->setDate(new \DateTime($request->request->get('date')));
            $event->setLocation($request->request->get('location'));
            $event->setSeats((int) $request->request->get('seats'));
            $event->setImage($request->request->get('image') ?: null);

            $this->em->persist($event);
            $this->em->flush();

            $this->addFlash('success', 'Event created successfully!');
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/event_form.html.twig', [
            'event' => null,
            'action' => 'Create',
        ]);
    }

    #[Route('/admin/events/{id}/edit', name: 'admin_event_edit', methods: ['GET', 'POST'])]
    public function editEvent(int $id, Request $request): Response
    {
        $event = $this->eventRepo->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        if ($request->isMethod('POST')) {
            $event->setTitle($request->request->get('title'));
            $event->setDescription($request->request->get('description'));
            $event->setDate(new \DateTime($request->request->get('date')));
            $event->setLocation($request->request->get('location'));
            $event->setSeats((int) $request->request->get('seats'));
            $event->setImage($request->request->get('image') ?: null);

            $this->em->flush();

            $this->addFlash('success', 'Event updated successfully!');
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/event_form.html.twig', [
            'event' => $event,
            'action' => 'Edit',
        ]);
    }

    #[Route('/admin/events/{id}/delete', name: 'admin_event_delete', methods: ['POST'])]
    public function deleteEvent(int $id): Response
    {
        $event = $this->eventRepo->find($id);
        if ($event) {
            $this->em->remove($event);
            $this->em->flush();
            $this->addFlash('success', 'Event deleted.');
        }
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/admin/events/{id}/reservations', name: 'admin_event_reservations')]
    public function eventReservations(int $id): Response
    {
        $event = $this->eventRepo->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found');
        }

        $reservations = $this->reservationRepo->findByEvent($event);

        return $this->render('admin/reservations.html.twig', [
            'event' => $event,
            'reservations' => $reservations,
        ]);
    }
}
