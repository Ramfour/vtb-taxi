<?php

namespace App\Enums;

enum UserRole: int
{
    case Employee = 1;
    case Manager = 2;
    case Admin = 3;
}
