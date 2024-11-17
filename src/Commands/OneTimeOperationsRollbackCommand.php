<?php

namespace App\Console\Commands;

use DB;
use Exception;
use Illuminate\Console\Command;
use TimoKoerber\LaravelOneTimeOperations\Models\Operation;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperationFile;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperationManager;
use TimoKoerber\LaravelOneTimeOperations\Jobs\OneTimeOperationRollbackJob;

class OneTimeOperationsRollbackCommand extends Command
{
    protected $signature = 'operations:rollback 
                            {name? : Name of specific operation}
                            {--tag= : Process only operations that have the given tag}
                            {--async : Force asynchronous rollback}
                            {--sync : Force synchronous rollback}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback one-time operations';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');
        $tag = $this->option('tag');
        $forceAsync = $this->option('async');
        $forceSync = $this->option('sync');

        if (($name && $tag) || (!$name && !$tag)) {
            $this->error('Please provide either a name or a tag, but not both and not empty.');
            return -1;
        }

        if ($forceAsync && $forceSync) {
            $this->error('Please provide either --async or --sync, not both.');
            return -1;
        }

        try {
            if ($name) {
                $this->rollbackByName($name, $forceAsync, $forceSync);
            } else {
                $this->rollbackByTag($tag, $forceAsync, $forceSync);
            }
        } catch (Exception $ex) {
            $this->error($ex->getMessage());
        }

        return 0;
    }

    protected function rollbackByName($name, $forceAsync, $forceSync)
    {
        $operationFile = OneTimeOperationManager::getOperationFileByName($name);

        if (!$operationFile) {
            $this->error("Operation file for '$name' not found.");
            return -1;
        }

        $this->processRollback($operationFile, $forceAsync, $forceSync);
        $this->deleteJob($name);
        $this->info('Operation rolled back successfully.');
    }

    protected function rollbackByTag($tag, $forceAsync, $forceSync)
    {
        $operations = OneTimeOperationManager::getAllOperationFiles()
            ->filter(function (OneTimeOperationFile $operationFile) use ($tag) {
                return $operationFile->getClassObject()->getTag() === $tag;
            });

        if ($operations->isEmpty()) {
            $this->info("No operations found with tag '$tag'.");
            return;
        }

        foreach ($operations as $operationFile) {
            $this->processRollback($operationFile, $forceAsync, $forceSync);
            $filename = pathinfo($operationFile->getPath(), PATHINFO_FILENAME);
            $this->deleteJob($filename);
            $this->info("Operation '$filename' rolled back successfully.");
        }
    }

    protected function processRollback(OneTimeOperationFile $operationFile, $forceAsync, $forceSync)
    {
        $operationClass = $operationFile->getClassObject();
        $isAsync = $forceAsync || (!$forceSync && $operationClass->isAsync());

        if ($isAsync) {
            OneTimeOperationRollbackJob::dispatch($operationFile->getOperationName())
                ->onConnection($operationClass->getConnection())
                ->onQueue($operationClass->getQueue());
        } else {
            OneTimeOperationRollbackJob::dispatchSync($operationFile->getOperationName());
        }
    }


    protected function deleteJob($filename)
    {
        DB::table('operations')->where('name', $filename)->delete();
    }
}
