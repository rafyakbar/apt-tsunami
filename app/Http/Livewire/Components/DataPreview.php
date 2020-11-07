<?php

namespace App\Http\Livewire\Components;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class DataPreview extends Component
{
    public $date = '-';

    protected $listeners = [
        'changeDate' => 'setDate'
    ];

    public function setDate($url)
    {
        parse_str( parse_url( $url, PHP_URL_QUERY), $array );
        $this->date = $array['date'];
    }

    private function data()
    {
        $resp = Http::get(env('DATA_URL', 'http://grafik.prediksi-tsunami.unesa.ac.id/data/plotsum.az-tdur.txt'));
        $data = ($resp->status() == '200')
            ? $resp->body()
            : null;

        if (!empty($data)) {
            $lines = preg_split('/\r\n|\r|\n/', $data);

            $data = [];
            foreach (preg_split('/\r\n|\r|\n/', $this->test) as $line) {
                $line = explode("\t", $line);
                $data[] = [
                    'stat' => $line[2],
                    'net' => '-',
                    'date' => '-',
                    'time' => '-',
                    'dist' => '-',
                    'az' => (float)(str_replace(',', '.', $line[0])),
                    'tdur' => (float)(str_replace(',', '.', $line[1]))
                ];
            }

            collect($lines)->each(function ($line) use (&$data) {
                $line = str_replace('  ', ' ', $line);
                $line = str_replace(' Stat', 'Stat', $line);

                $columStr = 'Stat Net Date Time Dist Az Tdur';

                if (!Str::contains($line, $columStr)) {
                    $fields = explode(' ', $line);

                    if (count($fields) > 6) {
                        $d = collect(explode(' ', $columStr))
                            ->mapWithKeys(function ($val, $key) use ($fields) {
                                $d = !in_array($val, ['Az', 'Tdur'])
                                    ? $fields[$key]
                                    : (float)$fields[$key];

                                return [strtolower($val) => $d];
                            })->toArray();

                        $data[] = $d;
                    }
                }
            });

            $linearCurveFit = false;

            $x = [];
            $y = [];

            $data = collect($data)
                ->groupBy('date')
                ->map(function ($data) {
                    $c = 0;

                    return $data->sortByDesc('time')
                        ->mapWithKeys(function ($d) use (&$c) {
                            return [$c++ => $d];
                        });
                })->sortByDesc(function ($val, $key) {
                    return $key;
                });

            return $data;
        }

        return [];
    }

    public function mount()
    {
        $this->date = $this->data()
            ->keys()[0];
    }

    public function render()
    {
        $data = $this->data()
            ->get($this->date);

        $keys = array_keys($data[0]);

        return view('livewire.components.data-preview', [
            'data' => $data,
            'keys' => $keys
        ]);
    }

    protected $test = '1,82	95,97	TPUB
2,23	32,27	SSE
40,21	91,28	SSLB
3,32	59,27	YHNB
41,72	85,45	YULB
30,61	79,64	NACB
6,89	73,23	YOJ
135,44	19,06	KDU
139,96	26,71	AULRC
140,33	28,9	QLP
144,48	32,86	CMSA
144,81	15,8	WB10
144,83	25,54	WR10
150,81	44,61	KNRA
150,82	37,39	AS31
150,89	42,25	AUALC
151,67	27,5	LCRK
152,88	36,23	YAPP
153,55	19,87	HTT
154,01	21,04	SDAN
155,43	40,71	SOEI
156,48	18,67	BBOO
157,08	21,23	MULG
162,66	15,35	FITZ
166,44	12	FORT
179,69	11,42	PSAD2
179,85	11,09	PSAC2
179,95	11,22	PSAB2
179,96	11,19	PSAA3
179,96	11,4	PSAB3
179,98	11,29	PSAA2
179,99	11,45	PSA00
180,01	11,47	PSAA1
180,08	11,92	PSAC1
180,15	10,8	PSAD3
184,03	29,73	NWAO
184,09	29,27	NWAO
186,69	32,05	AUBUS
186,84	22,65	MORW
193,28	18,8	GIRL
233,41	17,97	XMIS
233,42	17,07	XMIS
241,39	26,75	COCO
255,84	67,54	MNAI
271,53	74,58	BKNI
277,09	65,68	KOM
277,97	77,35	PSI
279,8	58,95	HALK
313,48	94,83	MHIT
315,16	71,6	PHRA
322,26	74,39	TNC
328,07	88,66	KMI
339,37	72,77	LZH
346,47	89,53	HKPS
350,84	79,01	WHN
356,83	79,99	KMNB';
}
