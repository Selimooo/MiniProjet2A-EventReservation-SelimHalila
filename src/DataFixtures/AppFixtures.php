<?php

namespace App\DataFixtures;

use App\Entity\Admin;
use App\Entity\Event;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ── Admin ──────────────────────────────────────────────────────────
        $admin = new Admin();
        $admin->setUsername('admin');
        $admin->setPassword(
            $this->hasher->hashPassword($admin, 'Admin@1234')
        );
        $manager->persist($admin);

        // ── Sample Events ──────────────────────────────────────────────────
        $events = [
            [
                'title'       => 'Tech Conference 2026',
                'description' => 'Annual technology conference featuring the latest in AI, cloud, and web development. Join 500+ developers for workshops and keynotes.',
                'date'        => new \DateTime('+30 days'),
                'location'    => 'Palais des Congrès, Sousse',
                'seats'       => 200,
                'image'       => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800',
            ],
            [
                'title'       => 'Symfony Workshop',
                'description' => 'Hands-on workshop covering Symfony 7, REST APIs, JWT authentication and modern PHP best practices.',
                'date'        => new \DateTime('+15 days'),
                'location'    => 'ISSAT Sousse, Salle Info 1',
                'seats'       => 40,
                'image'       => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=800',
            ],
            [
                'title'       => 'Cybersecurity Summit',
                'description' => 'Deep dive into modern authentication standards, passkeys, FIDO2 and zero-trust architecture.',
                'date'        => new \DateTime('+45 days'),
                'location'    => 'Hôtel Hasdrubal, Hammamet',
                'seats'       => 100,
                'image'       => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800',
            ],
            [
                'title'       => 'Startup Weekend Sousse',
                'description' => '54-hour startup event. Build, launch and pitch your idea to a panel of investors and mentors.',
                'date'        => new \DateTime('+60 days'),
                'location'    => 'TechPark Sousse',
                'seats'       => 80,
                'image'       => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?w=800',
            ],
        ];

        foreach ($events as $data) {
            $event = new Event();
            $event->setTitle($data['title']);
            $event->setDescription($data['description']);
            $event->setDate($data['date']);
            $event->setLocation($data['location']);
            $event->setSeats($data['seats']);
            $event->setImage($data['image']);
            $manager->persist($event);
        }

        $manager->flush();
    }
}
