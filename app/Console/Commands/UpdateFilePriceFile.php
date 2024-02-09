<?php

declare(strict_types = 1);

namespace App\Console\Commands;

use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UpdateFilePriceFile extends Command
{
    protected const URL = 'http://kievopt.com.ua/prices/prom-20703.yml';
    protected $signature = 'price:update';
    protected $description = 'Updates the price file';
    private string $newFilePath;
    private string $processingFilePath;
    private string $processedFilePath;
    private string $datesFilePath;

    public function __construct()
    {
        parent::__construct();

        $this->newFilePath = storage_path('app/kievoptFile.xml');
        $this->processingFilePath = storage_path('app/kievoptFileProcessing.xml');
        $this->processedFilePath = storage_path('app/kievoptFileProcessed.xml');
        $this->datesFilePath = storage_path('app/dates');
    }

    public function handle(): void
    {
        Log::info('[Price update]: Started');
        $t = microtime(true);
        $memory = memory_get_peak_usage(true) / 1024 / 1024;
        if (!File::put($this->newFilePath, file_get_contents(self::URL))) {
            Log::info('[Price update]: Unable to store the file from kievopt', ['url' => self::URL]);
            return;
        }
        Log::info('[Price update]: New file stored');

        $newDate = $this->getDate($this->newFilePath);

        if (!File::exists($this->datesFilePath)) {
            File::put($this->datesFilePath, $newDate);
        } else {
            $oldDate = File::get($this->datesFilePath);

            if ($newDate <= $oldDate) {
                Log::info('[Price update]: the new date isn\'t new', ['new_date' => $newDate, 'old_date' => $oldDate]);
                return;
            }
        }

        Log::info('[Price update]: Start processing');
        $processingFile = fopen($this->processingFilePath, 'a');
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

                    $el = str_replace($text, "{$text} BS-03", $el);
                }
            }

            if (Str::startsWith(trim($el), '<vendorCode>')) {
                preg_match('/<vendorCode>(.*?)<\/vendorCode>/si', $el, $match);
                $text = '';
                if ($match) {
                    $text = $match[1] ?? null;
                }
                $el = str_replace($text, "{$text}-BS-03", $el);
            }

            fwrite($processingFile, $el);
        }

        Log::info('[Price update]: Processed');
        if (fclose($processingFile)) {
            File::move($this->processingFilePath, $this->processedFilePath);
            Log::info('[Price update]: File moved');
        }

        Log::info('[Price update]: Finished!', ['spent' => microtime(true) - $t, 'memory' => $memory]);
    }

    protected function getItems(string $fileName): Generator
    {
        if ($file = fopen($fileName, 'r')) {
            while(!feof($file)) {
                yield fgets($file);
            }
            fclose($file);
        }
    }

    protected function getDate(string $fileName): ?string
    {
        $i = 0;
        if ($file = fopen($fileName, 'r')) {
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
