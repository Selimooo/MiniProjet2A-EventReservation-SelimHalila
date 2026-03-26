<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @extends ServiceEntityRepository<WebauthnCredential>
 */
class WebauthnCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

    public function saveCredential(User $user, PublicKeyCredentialSource $source, string $name = 'My Passkey'): void
    {
        $credential = new WebauthnCredential();
        $credential->setUser($user);
        $credential->setCredentialSource($source);
        $credential->setName($name);

        $this->getEntityManager()->persist($credential);
        $this->getEntityManager()->flush();
    }

    public function findByCredentialId(string $credentialId): ?WebauthnCredential
    {
        $all = $this->findAll();
        foreach ($all as $cred) {
            $source = $cred->getCredentialSource();
            if (base64_encode($source->getPublicKeyCredentialId()) === base64_encode($credentialId)) {
                return $cred;
            }
        }
        return null;
    }

    /**
     * Returns all PublicKeyCredentialSource for a given user (for WebAuthn allowCredentials list)
     */
    public function findAllForUser(User $user): array
    {
        $credentials = $this->findBy(['user' => $user]);
        return array_map(fn(WebauthnCredential $c) => $c->getCredentialSource(), $credentials);
    }
}
