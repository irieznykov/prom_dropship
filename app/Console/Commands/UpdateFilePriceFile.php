<?php

declare(strict_types = 1);

namespace App\Console\Commands;

use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UpdateFilePriceFile extends Command
{
    protected $signature = 'price:update';

    protected $description = 'Updates the price file';

    public function handle(): void
    {
        $t = microtime(true);
        $memory = memory_get_peak_usage(true) / 1024 / 1024;
        $newFile = storage_path('app/public/1234.xml');
        $newFileStream = fopen($newFile, 'a');
        $i = 1;
        foreach ($this->getItems(storage_path('app/public/123.xml')) as $el) {
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

            fwrite($newFileStream, $el);
        }

        fclose($newFileStream);

        $this->line(microtime(true) - $t);
        $this->line($memory);
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
}
