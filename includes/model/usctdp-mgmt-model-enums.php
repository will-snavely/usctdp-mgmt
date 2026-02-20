<?php

if (!defined('ABSPATH')) {
    exit;
}

enum Usctdp_Day_Of_Week: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;
}

enum Usctdp_Activity_Type: int
{
    case Clinic = 1;
    case Tournament = 2;
    case Camp = 3;
}

enum Usctdp_Age_Group: int
{
    case Junior = 1;
    case Adult = 2;
}

enum Usctdp_Session_Category: int
{
    case Junior_Beginner = 1;
    case Junior_Advanced = 2;
    case Adult = 3;
    case Cardio = 4;
    case Junior_Tournament = 5;
    case Adult_Tournament = 6;
}

enum Usctdp_Registration_Status: int
{
    case Pending = 1;
    case Confirmed = 2;
    case Voided = 3;
}
