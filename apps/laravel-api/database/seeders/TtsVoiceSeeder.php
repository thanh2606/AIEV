<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TtsVoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $googleVoices = [
            ["name" => "Zephyr", "style" => "trong trẻo, tươi sáng", "gender" => "nu", "f0" => 196],
            ["name" => "Puck", "style" => "vui tươi, hào hứng", "gender" => "nam", "f0" => 115],
            ["name" => "Charon", "style" => "truyền đạt, rành mạch", "gender" => "nam", "f0" => 126],
            ["name" => "Kore", "style" => "chắc chắn, dứt khoát", "gender" => "nu", "f0" => 207],
            ["name" => "Fenrir", "style" => "sôi nổi, dễ phấn khích", "gender" => "nam", "f0" => 128],
            ["name" => "Leda", "style" => "trẻ trung", "gender" => "nu", "f0" => 206],
            ["name" => "Orus", "style" => "chắc chắn, quả quyết", "gender" => "nam", "f0" => 130],
            ["name" => "Aoede", "style" => "nhẹ nhàng, thoáng", "gender" => "nu", "f0" => 180],
            ["name" => "Callirrhoe", "style" => "thư thái, dễ chịu", "gender" => "nu", "f0" => 203],
            ["name" => "Autonoe", "style" => "tươi sáng", "gender" => "nu", "f0" => 183],
            ["name" => "Enceladus", "style" => "nhiều hơi thở, thủ thỉ", "gender" => "nam", "f0" => 114],
            ["name" => "Iapetus", "style" => "rõ ràng, sáng tiếng", "gender" => "nam", "f0" => 137],
            ["name" => "Umbriel", "style" => "thư thái", "gender" => "nam", "f0" => 131],
            ["name" => "Algieba", "style" => "mượt mà", "gender" => "nam", "f0" => 109],
            ["name" => "Despina", "style" => "mượt mà, êm", "gender" => "nu", "f0" => 222],
            ["name" => "Erinome", "style" => "rõ ràng", "gender" => "nu", "f0" => 214],
            ["name" => "Algenib", "style" => "khàn, sạn", "gender" => "nam", "f0" => 126],
            ["name" => "Rasalgethi", "style" => "truyền đạt, học thuật", "gender" => "nam", "f0" => 141],
            ["name" => "Laomedeia", "style" => "hào hứng", "gender" => "nu", "f0" => 182],
            ["name" => "Achernar", "style" => "dịu, nhỏ nhẹ", "gender" => "nu", "f0" => 219],
            ["name" => "Alnilam", "style" => "chắc, đanh", "gender" => "nam", "f0" => 117],
            ["name" => "Schedar", "style" => "đều đều, điềm tĩnh", "gender" => "nam", "f0" => 136],
            ["name" => "Gacrux", "style" => "trầm, từng trải", "gender" => "nu", "f0" => 152],
            ["name" => "Pulcherrima", "style" => "chủ động, đẩy tới", "gender" => "trung-tinh", "f0" => 131],
            ["name" => "Achird", "style" => "thân thiện", "gender" => "nam", "f0" => 142],
            ["name" => "Zubenelgenubi", "style" => "tự nhiên, đời thường", "gender" => "nam", "f0" => 141],
            ["name" => "Vindemiatrix", "style" => "hiền hòa, êm ái", "gender" => "nu", "f0" => 191],
            ["name" => "Sadachbia", "style" => "sống động", "gender" => "nam", "f0" => 125],
            ["name" => "Sadaltager", "style" => "am hiểu, chững chạc", "gender" => "nam", "f0" => 125],
            ["name" => "Sulafat", "style" => "ấm áp", "gender" => "nu", "f0" => 231],
        ];

        foreach ($googleVoices as $voice) {
            \App\Models\TtsVoice::updateOrCreate(
                ['name' => $voice['name']],
                [
                    'engine' => 'gemini',
                    'title' => $voice['name'],
                    'label' => $voice['name'] . ' - ' . $voice['style'],
                    'gender' => $voice['gender'],
                    'f0' => $voice['f0'],
                    'kind' => 'preset',
                    'timbre_key' => strtolower($voice['name'])
                ]
            );
        }

        // Migrate cloned voices
        $libraryPath = config('aiev.repo_root') . '/assets/voices/library.json';
        if (file_exists($libraryPath)) {
            $raw = json_decode(file_get_contents($libraryPath), true);
            if (is_array($raw)) {
                foreach ($raw as $e) {
                    if (!isset($e['id'])) continue;
                    $refFile = isset($e['refFile']) ? $e['refFile'] : (isset($e['ref_file']) ? $e['ref_file'] : null);
                    
                    if ($refFile && file_exists(config('aiev.repo_root') . '/' . $refFile)) {
                        \App\Models\TtsVoice::updateOrCreate(
                            ['name' => $e['id']],
                            [
                                'engine' => 'vieneu',
                                'title' => $e['name'] ?? $e['id'],
                                'label' => ($e['name'] ?? $e['id']) . ' - giọng nhân bản',
                                'gender' => $e['gender'] ?? 'trung-tinh',
                                'f0' => 0,
                                'kind' => 'cloned',
                                'timbre_key' => 'cloned',
                                'note' => $e['note'] ?? '',
                                'ref_file' => $refFile,
                                'ref_duration_sec' => $e['refDurationSec'] ?? 0,
                            ]
                        );
                    }
                }
            }
        }
    }
}
