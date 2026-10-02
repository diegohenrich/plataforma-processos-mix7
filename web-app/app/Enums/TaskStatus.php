<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Blocked = 'blocked';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'A fazer',
            self::InProgress => 'Em andamento',
            self::Paused => 'Pausada',
            self::Blocked => 'Impedida',
            self::Completed => 'Concluída',
        };
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Todo => [self::InProgress, self::Blocked],
            self::InProgress => [self::Paused, self::Blocked, self::Completed],
            self::Paused => [self::InProgress, self::Blocked],
            self::Blocked => [self::InProgress, self::Paused],
            self::Completed => [],
        };
    }
}
