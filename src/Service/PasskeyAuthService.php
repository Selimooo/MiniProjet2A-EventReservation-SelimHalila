<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\WebauthnCredentialRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\AuthenticatorSelectionCriteria;

class PasskeyAuthService
{
    private string $rpId;
    private string $rpName;
    private string $origin;

    public function __construct(
        private WebauthnCredentialRepository $credRepo,
        private UserRepository $userRepo,
        private RequestStack $requestStack,
        private AuthenticatorAttestationResponseValidator $attestationValidator,
        private AuthenticatorAssertionResponseValidator $assertionValidator,
        string $appDomain,
        string $rpName
    ) {
        $this->rpId = $appDomain;
        $this->rpName = $rpName;
        $this->origin = 'https://' . $appDomain;
        // Allow http for localhost dev
        if ($appDomain === 'localhost' || str_starts_with($appDomain, '127.')) {
            $this->origin = 'http://' . $appDomain;
        }
    }

    public function getRegistrationOptions(User $user): array
    {
        $rp = PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId);

        $userEntity = PublicKeyCredentialUserEntity::create(
            $user->getEmail(),
            $user->getId()->toBinary(),
            $user->getUsername() ?? $user->getEmail()
        );

        $challenge = random_bytes(32);

        $excludeCredentials = array_map(
            fn($source) => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $source->getPublicKeyCredentialId()
            ),
            $this->credRepo->findAllForUser($user)
        );

        $options = PublicKeyCredentialCreationOptions::create(
            $rp,
            $userEntity,
            $challenge,
            [
                PublicKeyCredentialParameters::create('public-key', -7),   // ES256
                PublicKeyCredentialParameters::create('public-key', -257), // RS256
            ]
        )
        ->setTimeout(60000)
        ->excludeCredentials(...$excludeCredentials)
        ->setAuthenticatorSelection(
            AuthenticatorSelectionCriteria::create(
                null,
                AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED
            )
        );

        $session = $this->requestStack->getSession();
        $session->set('webauthn_registration', json_encode($options));

        return json_decode(json_encode($options), true);
    }

    public function verifyRegistration(array $credentialData, User $user): void
    {
        $session = $this->requestStack->getSession();
        $optionsData = $session->get('webauthn_registration');

        if (!$optionsData) {
            throw new \RuntimeException('No registration session found. Please restart registration.');
        }

        $options = PublicKeyCredentialCreationOptions::createFromString($optionsData);

        // Build the PublicKeyCredential from the client data
        $publicKeyCredential = $this->buildPublicKeyCredential($credentialData);

        if (!$publicKeyCredential->getResponse() instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Invalid response type for registration.');
        }

        $source = $this->attestationValidator->check(
            $publicKeyCredential->getResponse(),
            $options,
            $this->origin
        );

        $this->credRepo->saveCredential($user, $source);
        $session->remove('webauthn_registration');
    }

    public function getLoginOptions(): array
    {
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create($challenge)
            ->setRpId($this->rpId)
            ->setTimeout(60000)
            ->setUserVerification(
                PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED
            );

        $session = $this->requestStack->getSession();
        $session->set('webauthn_login', json_encode($options));

        return json_decode(json_encode($options), true);
    }

    public function verifyLogin(array $credentialData): User
    {
        $session = $this->requestStack->getSession();
        $optionsData = $session->get('webauthn_login');

        if (!$optionsData) {
            throw new \RuntimeException('No login session found. Please restart login.');
        }

        $options = PublicKeyCredentialRequestOptions::createFromString($optionsData);

        $publicKeyCredential = $this->buildPublicKeyCredential($credentialData);

        if (!$publicKeyCredential->getResponse() instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Invalid response type for login.');
        }

        // Find the credential in DB
        $credentialId = base64_decode(strtr($credentialData['rawId'], '-_', '+/'));
        $credentialEntity = $this->credRepo->findByCredentialId($credentialId);

        if (!$credentialEntity) {
            throw new \RuntimeException('Passkey not found. Please register first.');
        }

        $source = $this->assertionValidator->check(
            $credentialEntity->getCredentialSource(),
            $publicKeyCredential->getResponse(),
            $options,
            $this->origin,
            null
        );

        // Update the credential source (counter update)
        $credentialEntity->setCredentialSource($source);
        $credentialEntity->touch();

        $session->remove('webauthn_login');

        return $credentialEntity->getUser();
    }

    private function buildPublicKeyCredential(array $data): PublicKeyCredential
    {
        // This reconstructs a PublicKeyCredential from the JS response
        return PublicKeyCredential::createFromArray($data);
    }
}
