<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepo,
        private ReservationRepository $reservationRepo,
        private EntityManagerInterface $em
    ) {}

    // ─── Dashboard ───────────────────────────────────────────────────────────

    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $events = $this->eventRepo->findAll();
        $totalReservations = count($this->reservationRepo->findAll());

        return $this->json([
            'totalEvents' => count($events),
            'totalReservations' => $totalReservations,
            'events' => array_map(fn(Event $e) => [
                'id' => $e->getId(),
                'title' => $e->getTitle(),
                'date' => $e->getDate()->format('Y-m-d H:i'),
                'seats' => $e->getSeats(),
                'reservations' => $e->getReservations()->count(),
            ], $events),
        ]);
    }

    // ─── Events CRUD ─────────────────────────────────────────────────────────

    #[Route('/events', methods: ['GET'])]
    public function listEvents(): JsonResponse
    {
        $events = $this->eventRepo->findAll();
        return $this->json(array_map(fn(Event $e) => $this->serializeEvent($e), $events));
    }

    #[Route('/events', methods: ['POST'])]
    public function createEvent(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['title'], $data['description'], $data['date'], $data['location'], $data['seats'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $event = new Event();
        $event->setTitle($data['title']);
        $event->setDescription($data['description']);
        $event->setDate(new \DateTime($data['date']));
        $event->setLocation($data['location']);
        $event->setSeats((int) $data['seats']);
        $event->setImage($data['image'] ?? null);

        $this->em->persist($event);
        $this->em->flush();

        return $this->json($this->serializeEvent($event), Response::HTTP_CREATED);
    }

    #[Route('/events/{id}', methods: ['GET'])]
    public function getEvent(int $id): JsonResponse
    {
        $event = $this->eventRepo->find($id);
        if (!$event) {
            return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serializeEvent($event));
    }

    #[Route('/events/{id}', methods: ['PUT'])]
    public function updateEvent(int $id, Request $request): JsonResponse
    {
        $event = $this->eventRepo->find($id);
        if (!$event) {
            return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) $event->setTitle($data['title']);
        if (isset($data['description'])) $event->setDescription($data['description']);
        if (isset($data['date'])) $event->setDate(new \DateTime($data['date']));
        if (isset($data['location'])) $event->setLocation($data['location']);
        if (isset($data['seats'])) $event->setSeats((int) $data['seats']);
        if (array_key_exists('image', $data)) $event->setImage($data['image']);

        $this->em->flush();

        return $this->json($this->serializeEvent($event));
    }

    #[Route('/events/{id}', methods: ['DELETE'])]
    public function deleteEvent(int $id): JsonResponse
    {
        $event = $this->eventRepo->find($id);
        if (!$event) {
            return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($event);
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Event deleted']);
    }

    // ─── Reservations per event ───────────────────────────────────────────────

    #[Route('/events/{id}/reservations', methods: ['GET'])]
    public function eventReservations(int $id): JsonResponse
    {
        $event = $this->eventRepo->find($id);
        if (!$event) {
            return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);
        }

        $reservations = $this->reservationRepo->findByEvent($event);

        return $this->json(array_map(fn($r) => [
            'id' => $r->getId(),
            'name' => $r->getName(),
            'email' => $r->getEmail(),
            'phone' => $r->getPhone(),
            'createdAt' => $r->getCreatedAt()->format('Y-m-d H:i:s'),
        ], $reservations));
    }

    private function serializeEvent(Event $e): array
    {
        return [
            'id' => $e->getId(),
            'title' => $e->getTitle(),
            'description' => $e->getDescription(),
            'date' => $e->getDate()->format('Y-m-d H:i:s'),
            'location' => $e->getLocation(),
            'seats' => $e->getSeats(),
            'availableSeats' => $e->getAvailableSeats(),
            'image' => $e->getImage(),
            'reservationsCount' => $e->getReservations()->count(),
        ];
    }
}
