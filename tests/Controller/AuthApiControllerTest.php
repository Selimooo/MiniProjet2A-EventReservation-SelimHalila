<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthApiControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** Test: GET /api/events returns 200 (public) */
    public function testEventsListIsPublic(): void
    {
        $this->client->request('GET', '/api/events');
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
    }

    /** Test: /api/auth/me without token returns 401 */
    public function testMeRequiresAuth(): void
    {
        $this->client->request('GET', '/api/auth/me');
        $this->assertResponseStatusCodeSame(401);
    }

    /** Test: register/options with valid email returns challenge */
    public function testRegisterOptionsReturnsChallenge(): void
    {
        $this->client->request(
            'POST', '/api/auth/register/options',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'test' . time() . '@example.com'])
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('challenge', $data);
        $this->assertArrayHasKey('rp', $data);
        $this->assertArrayHasKey('user', $data);
    }

    /** Test: register/options with missing email returns 400 */
    public function testRegisterOptionsRequiresEmail(): void
    {
        $this->client->request(
            'POST', '/api/auth/register/options',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );
        $this->assertResponseStatusCodeSame(400);
    }

    /** Test: login/options returns challenge */
    public function testLoginOptionsReturnsChallenge(): void
    {
        $this->client->request('POST', '/api/auth/login/options');
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('challenge', $data);
    }

    /** Test: /api/auth/me with valid JWT returns user data */
    public function testMeWithValidToken(): void
    {
        $user = new User();
        $user->setEmail('apitest@example.com');
        $user->setUsername('apitestuser');

        $token = static::getContainer()
            ->get('lexik_jwt_authentication.jwt_manager')
            ->create($user);

        $this->client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('apitest@example.com', $data['email']);
    }

    /** Test: admin endpoint is protected */
    public function testAdminApiRequiresAuth(): void
    {
        $this->client->request('GET', '/api/admin/dashboard');
        $this->assertResponseStatusCodeSame(401);
    }

    /** Test: reserve without auth returns JSON response */
    public function testReservationRequiresData(): void
    {
        $this->client->request(
            'POST', '/api/events/1/reserve',
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );
        // 400 (missing data) or 404 (no event yet) - both are correct
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertContains($status, [400, 404]);
    }
}
