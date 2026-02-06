<?php

if (! defined('ABSPATH')) {
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

enum Usctdp_Transaction_Kind: int
{
    case Payment = 1;
    case ClubCredit = 2;
}

enum Usctdp_Transaction_Method: int
{
    case Check = 1;
    case Cash = 2;
    case WebStore = 3;
    case PayPal = 4;
}

enum Usctdp_Check_Status: int
{
    case None = 0;
    case Pending = 1;
    case Voided = 2;
    case Cleared = 3;
    case Bounced = 4;
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
