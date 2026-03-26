<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/events')]
class EventApiController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepo,
        private ReservationRepository $reservationRepo,
        private EntityManagerInterface $em
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $events = $this->eventRepo->findUpcoming();
        return $this->json(array_map(fn(Event $e) => $this->serializeEvent($e), $events));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $event = $this->eventRepo->find($id);
        if (!$event) {
            return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serializeEvent($event));
    }

    #[Route('/{id}/reserve', methods: ['POST'])]
    public function reserve(int $id, Request $request): JsonResponse
    {
        $event = $this->eventRepo->find($id);
        if (!$event) {
            return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);
        }

        if ($event->getAvailableSeats() <= 0) {
            return $this->json(['error' => 'No seats available'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;

        if (!$name || !$email || !$phone) {
            return $this->json(['error' => 'Name, email and phone are required'], Response::HTTP_BAD_REQUEST);
        }

        $reservation = new Reservation();
        $reservation->setEvent($event);
        $reservation->setName($name);
        $reservation->setEmail($email);
        $reservation->setPhone($phone);

        // Attach to authenticated user if available
        $user = $this->getUser();
        if ($user) {
            $reservation->setUser($user);
        }

        $this->em->persist($reservation);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Reservation confirmed!',
            'reservation' => [
                'id' => $reservation->getId(),
                'event' => $event->getTitle(),
                'name' => $reservation->getName(),
                'email' => $reservation->getEmail(),
                'createdAt' => $reservation->getCreatedAt()->format('Y-m-d H:i:s'),
            ]
        ], Response::HTTP_CREATED);
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
        ];
    }
}
