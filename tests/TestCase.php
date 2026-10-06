<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /** Connessioni dei model che in produzione stanno su altri database dello stesso server. */
    private const EXTERNAL_CONNECTIONS = ['mysql_unicooam', 'mysql_proforma'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->shareDefaultConnectionWithExternalDatabases();
    }

    /**
     * Nei test le connessioni "mysql_unicooam" e "mysql_proforma" puntano allo stesso database
     * in memoria della connessione di default, così non si tocca mai il MySQL reale.
     */
    private function shareDefaultConnectionWithExternalDatabases(): void
    {
        foreach (self::EXTERNAL_CONNECTIONS as $name) {
            config(["database.connections.{$name}" => config('database.connections.'.config('database.default'))]);

            DB::purge($name);
            DB::connection($name)->setPdo(DB::connection()->getPdo());
            DB::connection($name)->setReadPdo(DB::connection()->getReadPdo());
        }
    }
}
