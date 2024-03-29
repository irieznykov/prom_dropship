<?php

declare(strict_types = 1);

namespace App\Console\Commands;

use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UpdateFilePriceFile extends Command
{
    protected $signature = 'price:update';
    protected $description = 'Updates the price file';
    private string $newFilePath;
    private string $processingFilePath;
    private string $processedFilePath;
    private array $config;

    public function __construct()
    {
        parent::__construct();

        $this->newFilePath = 'kievoptFile.xml';
        $this->processingFilePath = 'kievoptFileProcessing.xml';
        $this->processedFilePath = 'kievoptFileProcessed.xml';
        $this->config = Config::get('price');
    }

    public function handle(): void
    {
        Log::info('[Price update]: Started');
        $t = microtime(true);
        $memory = memory_get_peak_usage(true) / 1024 / 1024;
        if (!Storage::put($this->newFilePath, file_get_contents($this->config['kievopt']['url']))) {
            Log::info('[Price update]: Unable to store the file from kievopt', ['url' => $this->config['kievopt']['url']]);
            return;
        }
        Log::info('[Price update]: New file stored');

        $newDate = $this->getDate($this->newFilePath);

        if (($oldDate = DB::table('date')->first()) && $newDate <= $oldDate->date) {
            Log::info('[Price update]: the new date isn\'t new', ['new_date' => $newDate, 'old_date' => $oldDate]);
            return;
        }

        Log::info('[Price update]: Start processing');
        if ($oldDate) {
            DB::table('date')->where('id', $oldDate->id)
                ->update(['date' => $newDate]);
        } else {
            DB::table('date')->insert(['date' => $newDate]);
        }
        Log::info('[Price update]: Date updated');

        $processingFile = fopen(Storage::path($this->processingFilePath), 'a');
        $i = 1;
        foreach ($this->getItems($this->newFilePath) as $el) {
            if (!$el) {
                break;
            }

            if (Str::startsWith(trim($el), '<name>')) {
                if ($i === 1) {
                    $i++;
                } else {
                    preg_match('/<name>(.*?)<\/name>/si', $el, $match);
                    $text = '';
                    if ($match) {
                        $text = $match[1] ?? null;
                    }

                    $el = str_replace($text, "{$text}{$this->config['kievopt']['name']}", $el);
                }
            }

            if (Str::startsWith(trim($el), '<vendorCode>')) {
                preg_match('/<vendorCode>(.*?)<\/vendorCode>/si', $el, $match);
                $text = '';
                if ($match) {
                    $text = $match[1] ?? null;
                }
                $el = str_replace($text, "{$text}{$this->config['kievopt']['sku']}", $el);
            }

            fwrite($processingFile, $el);
        }

        Log::info('[Price update]: Processed');
        if (fclose($processingFile)) {
            Storage::disk('s3')->put($this->processedFilePath, Storage::get($this->processingFilePath));
            Log::info('[Price update]: File moved');
        }

        Log::info('[Price update]: Finished!', ['spent' => microtime(true) - $t, 'memory' => $memory]);
    }

    protected function getItems(string $fileName): Generator
    {
        if ($file = fopen(Storage::path($fileName), 'r')) {
            while(!feof($file)) {
                yield fgets($file);
            }
            fclose($file);
        }
    }

    protected function getDate(string $fileName): ?string
    {
        $i = 0;
        if ($file = fopen(Storage::path($fileName), 'r')) {
            while (!feof($file)) {
                $line = fgets($file);
                if (Str::startsWith(trim($line), '<yml_catalog')) {
                    preg_match('/<yml_catalog date=\"(.*?)\">/si', $line, $match);
                    return $match[1] ?? null;
                }
                if (++$i > 3) {
                    return null;
                }
            }
        }

        return null;
    }
}
