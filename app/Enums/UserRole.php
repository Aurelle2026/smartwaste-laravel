<?php

namespace App\Enums;

enum UserRole: string
{
    case Citoyen = 'citoyen';
    case Recycleur = 'recycleur';
    case Mairie = 'mairie';
    case Isacam = 'isacam';
    case Admin = 'admin';
    case Bnd = 'bnd';

    /** Rôles qui peuvent s'inscrire eux-mêmes */
    public static function registrable(): array
    {
        return [self::Citoyen->value, self::Recycleur->value];
    }

    public static function values(): array
    {
        return array_map(fn ($r) => $r->value, self::cases());
    }
}
