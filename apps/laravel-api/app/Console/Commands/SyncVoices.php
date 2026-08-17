<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\TtsVoice;
use Illuminate\Support\Facades\File;

class SyncVoices extends Command
{
    protected $signature = 'aiev:sync-voices';
    protected $description = 'Sync TTS voices from Gemini API and Local ViENEU Engine';

    public function handle()
    {
        $this->info('Syncing ViENEU preset voices...');
        $presetCount = 0;
        try {
            $voicesJsonFile = '/home/thanh/Documents/AIEV/voices.json';
            if (File::exists($voicesJsonFile)) {
                $rawContent = File::get($voicesJsonFile);
                // The python script might output some stderr text before the JSON.
                // We extract the last line which contains the JSON.
                $lines = explode("\n", trim($rawContent));
                $jsonLine = end($lines);
                
                $data = json_decode($jsonLine, true);
                $voices = $data['voices'] ?? [];
                
                $genderWord = ['nam' => 'nam', 'nu' => 'nữ'];
                $regionWord = ['bac' => 'miền Bắc', 'trung' => 'miền Trung', 'nam' => 'miền Nam'];
                $styleWord = [
                    'tin-tuc' => 'phong cách tin tức',
                    'tu-nhien' => 'phong cách tự nhiên',
                    'ke-chuyen' => 'phong cách kể chuyện',
                ];

                foreach ($voices as $row) {
                    if (empty($row['name'])) continue;
                    
                    $gender = (isset($row['gender']) && $row['gender'] === 'nu') ? 'nu' : 'nam';
                    $region = in_array($row['region'] ?? '', ['bac', 'trung', 'nam']) ? $row['region'] : null;
                    
                    $parts = array_filter([
                        $genderWord[$gender] ?? '',
                        $region ? ($regionWord[$region] ?? '') : '',
                        $styleWord[$row['style'] ?? ''] ?? ''
                    ]);

                    TtsVoice::updateOrCreate(
                        ['engine' => 'vieneu', 'name' => $row['name']],
                        [
                            'title' => $row['name'],
                            'label' => $row['name'] . ' - ' . implode(', ', $parts),
                            'gender' => $gender,
                            'f0' => 0,
                            'kind' => 'preset',
                            'region' => $region,
                            'timbre_key' => $row['style'] ?? '',
                            'note' => '',
                        ]
                    );
                    $presetCount++;
                }
            } else {
                $this->error('voices.json not found!');
            }
        } catch (\Exception $e) {
            $this->error('Failed to fetch ViENEU presets: ' . $e->getMessage());
        }

        $this->info("Synced $presetCount ViENEU preset voices.");

        $this->info('Syncing ViENEU cloned voices...');
        $clonedCount = 0;
        $clonedDir = base_path('../../.runtime/voices/cloned');
        if (File::exists($clonedDir) && File::isDirectory($clonedDir)) {
            $directories = File::directories($clonedDir);
            foreach ($directories as $dir) {
                $metaPath = $dir . '/meta.json';
                if (File::exists($metaPath)) {
                    try {
                        $meta = json_decode(File::get($metaPath), true);
                        if (isset($meta['id'], $meta['name'])) {
                            TtsVoice::updateOrCreate(
                                ['engine' => 'vieneu', 'name' => $meta['id']],
                                [
                                    'title' => $meta['name'],
                                    'label' => $meta['name'] . ' - giọng nhân bản',
                                    'gender' => $meta['gender'] ?? 'nam',
                                    'f0' => 0,
                                    'kind' => 'cloned',
                                    'region' => null,
                                    'timbre_key' => 'cloned',
                                    'note' => $meta['note'] ?? '',
                                ]
                            );
                            $clonedCount++;
                        }
                    } catch (\Exception $e) {
                        // skip
                    }
                }
            }
        }
        
        $this->info("Synced $clonedCount ViENEU cloned voices.");
        $this->info('Done!');
    }
}
