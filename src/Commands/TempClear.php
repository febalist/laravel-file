<?php

namespace Febalist\Laravel\File\Commands;

use Febalist\Laravel\File\File;
use Illuminate\Console\Command;
use Storage;

class TempClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'temp:clear {--hours=24}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear temp directory';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $timestamp = time() - $this->option('hours') * 60 * 60;
        $directory = File::tempDirectory();
        $storage = Storage::disk('local');

        $files = $storage->files($directory);
        foreach ($files as $path) {
            $modified = $storage->lastModified($path);
            if ($modified < $timestamp) {
                $storage->delete($path);
            }
        }
    }
}
