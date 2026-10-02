<?php

namespace App\Enums;

enum DemandStatus: string
{
    case Received = 'received';
    case Planning = 'planning';
    case InProgress = 'in_progress';
    case InternalReview = 'internal_review';
    case ClientApproval = 'client_approval';
    case Adjustments = 'adjustments';
    case Delivery = 'delivery';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Demanda recebida',
            self::Planning => 'Planejamento',
            self::InProgress => 'Em execução',
            self::InternalReview => 'Revisão interna',
            self::ClientApproval => 'Aprovação do cliente',
            self::Adjustments => 'Ajustes',
            self::Delivery => 'Entrega',
            self::Completed => 'Concluída',
        };
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Received => [self::Planning],
            self::Planning => [self::InProgress],
            self::InProgress => [self::InternalReview],
            self::InternalReview => [self::InProgress, self::ClientApproval],
            self::ClientApproval => [self::Adjustments, self::Delivery],
            self::Adjustments => [self::ClientApproval],
            self::Delivery => [self::Completed],
            self::Completed => [],
        };
    }

    /** @return list<self> */
    public function nextByManagement(): array
    {
        return $this === self::ClientApproval ? [] : $this->next();
    }
}
