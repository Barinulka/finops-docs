<?php

namespace App\Service\Audit;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Enum\AuditAction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AuditLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function log(
        AuditAction $action,
        string $entityType,
        ?string $entityId = null,
        ?string $message = null,
        array $oldValues = [],
        array $newValues = [],
        array $context = [],
        ?User $actor = null,
    ): AuditLog {
        $request = $this->requestStack->getCurrentRequest();
        $currentUser = $this->security->getUser();

        if ($actor === null && $currentUser instanceof User) {
            $actor = $currentUser;
        }

        $auditLog = new AuditLog();
        $auditLog
            ->setAction($action)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setMessage($message)
            ->setOldValues($oldValues)
            ->setNewValues($newValues)
            ->setContext($context)
            ->setActor($actor)
            ->setIpAddress($request?->getClientIp())
            ->setUserAgent($request?->headers->get('User-Agent'));

        $this->entityManager->persist($auditLog);

        return $auditLog;
    }

    public function created(
        string $entityType,
        ?string $entityId,
        array $newValues = [],
        array $context = [],
    ): AuditLog {
        return $this->log(AuditAction::Created, $entityType, $entityId, newValues: $newValues, context: $context);
    }

    public function updated(
        string $entityType,
        ?string $entityId,
        array $oldValues = [],
        array $newValues = [],
        array $context = [],
    ): AuditLog {
        return $this->log(AuditAction::Updated, $entityType, $entityId, oldValues: $oldValues, newValues: $newValues, context: $context);
    }

    public function deleted(
        string $entityType,
        ?string $entityId,
        array $oldValues = [],
        array $context = [],
    ): AuditLog {
        return $this->log(AuditAction::Deleted, $entityType, $entityId, oldValues: $oldValues, context: $context);
    }

    public function uploaded(
        string $entityType,
        ?string $entityId,
        array $context = [],
    ): AuditLog {
        return $this->log(AuditAction::Uploaded, $entityType, $entityId, context: $context);
    }

    public function parsed(
        string $entityType,
        ?string $entityId,
        array $newValues = [],
        array $context = [],
    ): AuditLog {
        return $this->log(AuditAction::Parsed, $entityType, $entityId, newValues: $newValues, context: $context);
    }

    public function confirmed(
        string $entityType,
        ?string $entityId,
        array $context = [],
    ): AuditLog {
        return $this->log(AuditAction::Confirmed, $entityType, $entityId, context: $context);
    }

    public function failed(
        string $entityType,
        ?string $entityId,
        string $message,
        array $context = [],
    ): AuditLog {
        return $this->log(AuditAction::Failed, $entityType, $entityId, message: $message, context: $context);
    }
}
