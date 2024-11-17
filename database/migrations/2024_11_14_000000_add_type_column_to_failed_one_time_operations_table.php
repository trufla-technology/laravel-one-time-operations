<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperationManager;

class AddTypeColumnToFailedOperationsTable extends Migration
{
    protected string $name;

    public function __construct()
    {
        $this->name = 'failed_' . OneTimeOperationManager::getTableName();
    }

    public function up(): void
    {
        if (!Schema::hasColumn($this->name, 'type')) {
            Schema::table($this->name, function (Blueprint $table) {
                $table->string('type')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable($this->name) && Schema::hasColumn($this->name, 'type')) {
            Schema::table($this->name, function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
}
