<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| VPN Panel scheduled tasks
|--------------------------------------------------------------------------
| Every minute : VPN server + MikroTik status checks
| Daily 02:00   : MikroTik devices config backup
| Daily 03:00   : database dump
| Daily 03:30   : managed files (chap-secrets, nginx) backup + retention
| Weekly Sun 04:00 : prune old audit logs (retention 90 days)
*/

Schedule::command('vpnpanel:monitor')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('vpnpanel:backup-mikrotiks')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('vpnpanel:backup-database')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('vpnpanel:backup-files')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('vpnpanel:prune-audit-logs --days=90')
    ->weeklyOn(0, '04:00')
    ->withoutOverlapping();