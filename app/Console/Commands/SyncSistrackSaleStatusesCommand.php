<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Services\SistrackSyncService;
use Illuminate\Console\Command;

class SyncSistrackSaleStatusesCommand extends Command
{
    protected $signature = 'sistrack:sync-statuses
                            {--all : Incluir también entregadas/devoluciones (re-sync)}
                            {--limit=500 : Máximo de ventas a sincronizar}';

    protected $description = 'Sincroniza estados de envío desde Sistrack hacia las ventas locales';

    public function handle(SistrackSyncService $sistrack): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $query = $this->option('all')
            ? Sale::query()
                ->with('shippingCarrier')
                ->where('sistrack_status', Sale::SISTRACK_SENT)
                ->whereNotNull('sistrack_external_id')
                ->where('status', '!=', Sale::STATUS_VOIDED)
            : Sale::query()
                ->with('shippingCarrier')
                ->pendingSistrackStatusSync();

        $sales = $query->orderBy('sold_at')->limit($limit)->get();

        if ($sales->isEmpty()) {
            $this->info('No hay ventas pendientes de sincronizar.');

            return self::SUCCESS;
        }

        $this->info('Sincronizando '.$sales->count().' venta(s)…');

        $result = $sistrack->syncStatuses($sales);

        $this->info('Actualizadas: '.count($result['updated']));
        $this->info('Sin cambio: '.count($result['unchanged']));
        $this->info('Omitidas: '.count($result['skipped']));
        $this->info('Fallidas: '.count($result['failed']));

        foreach (array_slice($result['failed'], 0, 10) as $row) {
            $this->error($row['sale']->number.': '.$row['error']);
        }

        return count($result['failed']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
