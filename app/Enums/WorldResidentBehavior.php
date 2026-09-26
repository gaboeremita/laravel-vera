<?php

namespace App\Enums;

enum WorldResidentBehavior: string
{
    case Stationary = 'stationary';
    case Roam = 'roam';
    case Autonomous = 'autonomous';
    case Route = 'route';
}
