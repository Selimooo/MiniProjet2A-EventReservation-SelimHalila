<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasskeyAuthService;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenManagerInterface $refreshManager,
        private UserRepository $userRepository
    ) {}

    // ─── Passkey Registration ───────────────────────────────────────────────

    #[Route('/register/options', methods: ['POST'])]
    public function registerOptions(Request $request, PasskeyAuthService $passkeyService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Valid email required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOrCreate($email);

        try {
            $options = $passkeyService->getRegistrationOptions($user);
            return $this->json($options);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/register/verify', methods: ['POST'])]
    public function registerVerify(Request $request, PasskeyAuthService $passkeyService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $credential = $data['credential'] ?? null;

        if (!$email || !$credential) {
            return $this->json(['error' => 'Email and credential required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $passkeyService->verifyRegistration($credential, $user);

            $jwt = $this->jwtManager->create($user);
            $refresh = $this->refreshManager->createForUser($user);

            return $this->json([
                'success' => true,
                'token' => $jwt,
                'refresh_token' => $refresh->getRefreshToken(),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    // ─── Passkey Login ───────────────────────────────────────────────────────

    #[Route('/login/options', methods: ['POST'])]
    public function loginOptions(PasskeyAuthService $passkeyService): JsonResponse
    {
        try {
            return $this->json($passkeyService->getLoginOptions());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login/verify', methods: ['POST'])]
    public function loginVerify(Request $request, PasskeyAuthService $passkeyService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $credential = $data['credential'] ?? null;

        if (!$credential) {
            return $this->json(['error' => 'Credential required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $passkeyService->verifyLogin($credential);

            $jwt = $this->jwtManager->create($user);
            $refresh = $this->refreshManager->createForUser($user);

            return $this->json([
                'success' => true,
                'token' => $jwt,
                'refresh_token' => $refresh->getRefreshToken(),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'username' => $user->getUsername(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }
    }

    // ─── Classic password login (for users with password set) ───────────────

    #[Route('/login/password', methods: ['POST'])]
    public function loginWithPassword(
        Request $request,
        UserPasswordHasherInterface $hasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['error' => 'Email and password required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !$user->getPassword() || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $jwt = $this->jwtManager->create($user);
        $refresh = $this->refreshManager->createForUser($user);

        return $this->json([
            'success' => true,
            'token' => $jwt,
            'refresh_token' => $refresh->getRefreshToken(),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
            ]
        ]);
    }

    // ─── Profile ─────────────────────────────────────────────────────────────

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ]);
    }
}
