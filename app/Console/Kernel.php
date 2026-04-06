<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('pedidos:notificar')->everyMinute();
        $schedule->command('recordatorios:enviar')->everyMinute();
        $schedule->command('recordatorios:ventana')->everyMinute();
        $schedule->command('clientes:seguimiento')->hourly();
        $schedule->call(fn() => \App\Models\Carrito::where('updated_at', '<', now()->subDays(30))->delete())->daily();
        $schedule->command('queue:work --stop-when-empty --tries=1')->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
