<?php

namespace App\Http\Controllers\Api;

use App\Charts\TdurAzimuth;
use App\Http\Controllers\Controller;
use DrQue\PolynomialRegression;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Phpml\Regression\LeastSquares;

class ChartController extends Controller
{
    public static function data()
    {
        $resp = Http::get(env('DATA_URL', 'http://grafik.prediksi-tsunami.unesa.ac.id/data/plotsum.az-tdur.txt'));
        $data = ($resp->status() == '200')
            ? $resp->body()
            : self::$txt;

        $lines = preg_split('/\r\n|\r|\n/', $data);

        $data = [];
        foreach (preg_split('/\r\n|\r|\n/', self::$test) as $line) {
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

    public function tdurAzamuth()
    {
        $data = self::data();

        // Storage::disk('public')->put('data.json', json_encode($data));

        $linearCurveFit = false;

        $x = [];
        $y = [];

        $data = $data->get((empty(request()->date) ? $data->keys()[0] : request()->date))
            ->map(function ($data) use (&$x, &$y, $linearCurveFit) {
                if ($linearCurveFit) {
                    $x[] = [$data['az']];
                    $y[] = $data['tdur'];
                } else {
                    $x[] = $data['az'];
                    $y[] = $data['tdur'];
                }

                return [
                    'x' => $data['az'],
                    'y' => $data['tdur'],
                    'r' => 2
                ];
            });

        $rSquaredData = [];

        if ($linearCurveFit) {
            $regression = new LeastSquares();
            $regression->train($x, $y);
        } else {
            bcscale(10);
            $polynomial = new PolynomialRegression(5);
            foreach ($x as $key => $_x) {
                $polynomial->addData($x[$key], $y[$key]);
                $rSquaredData[] = [$x[$key], $y[$key]];
            }
            $coefficients = $polynomial->getCoefficients();
        }

        $_ = [];
        $minX = (int)$data->min('x');
        $maxX = (int)$data->max('x');
        $step = (($maxX - $minX) / 1000) * 5;
        for ($c = $minX; $c < $maxX; $c += $step) {
            if ($linearCurveFit) {
                $_[] = [
                    'x' => $c,
                    'y' => $regression->predict([$c])
                ];
            } else {
                $y = 0;
                foreach ($coefficients as $power => $coefficient) {
                    $y += (($power == 0)
                        ? $coefficient
                        : (pow($c, $power) * $coefficient));
                }

                $_[] = [
                    'x' => $c,
                    'y' => $y
                ];
            }
        }

        $chart = new TdurAzimuth();
        $chart->dataset('Trend', 'line', $_)
            ->options([
                'fill' => false,
                'backgroundColor' => 'blue',
                'borderColor' => 'blue',
                'pointRadius' => 0.5,
                'borderWidth' => 1
            ]);
        $chart->dataset('Data', 'bubble', $data)
            ->options([
                'backgroundColor' => '#000000'
            ]);
        $chart->dataset('R-squared = '.number_format($polynomial->RSquared($rSquaredData, $coefficients), 4), '', []);

        return $chart->api();
    }

    public static $test = '1,82	95,97	TPUB
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

    public static $txt = ' Stat  Net Date  Time Dist  Az Tdur untuk evid 20200906213434
ARPR GE 20/09/06 21:37:43.2 13.1 282 48.67956
CSS GE 20/09/06 21:38:40.3 17.6 268 39.799343
TIRR GE 20/09/06 21:39:15.7 21.1 297 24.84
KTHA GE 20/09/06 21:39:57.0 25.4 277 6.45
STU GE 20/09/06 21:41:21.5 34.6 304 4.6
WLF GE 20/09/06 21:41:41.4 36.6 305 3.2
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200907174902
BBJI GE 20/09/07 17:49:48.7 3.0 44 48.931286
JAGI GE 20/09/07 17:51:05.3 8.6 83 14.4505
TOLI2 GE 20/09/07 17:53:20.5 18.6 55 28.030605
TNTI GE 20/09/07 17:54:18.9 24.1 66 12.1505
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200908004522
FAKI GE 20/09/08 00:46:11.5 3.1 52 49.6027
SANI GE 20/09/08 00:46:31.5 4.7 306 3.9005
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200908025809
TOLI2 GE 20/09/08 03:03:50.7 27.1 195 1.9505
VSU GE 20/09/08 03:09:35.7 72.4 327 2.15
ARPR GE 20/09/08 03:09:39.8 72.7 306 2.95
PABE GE 20/09/08 03:09:52.2 75.0 325 1.9
TIRR GE 20/09/08 03:10:05.5 77.3 314 2.35
CSS GE 20/09/08 03:10:08.7 78.0 303 2.3
EIL GE 20/09/08 03:10:15.2 79.0 298 2.35
PSZ GE 20/09/08 03:10:25.2 80.9 320 3.6
MORC GE 20/09/08 03:10:26.7 81.2 322 3.25
FLT1 GE 20/09/08 03:10:35.9 83.0 327 1.9
FALKS GE 20/09/08 03:10:38.0 83.4 326 2.15
KTHA GE 20/09/08 03:10:44.6 84.8 308 3.55
IBBN GE 20/09/08 03:10:45.1 84.7 328 2.15
UJAP GE 20/09/08 03:10:08.9 77.7 300 2.95
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200908104425
MATE GE 20/09/08 10:48:12.3 16.1 286 2.3
MARCO GE 20/09/08 10:48:21.5 16.7 285 10.65
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200909034119
FAKI GE 20/09/09 03:43:27.7 8.9 140 27.862064
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200909071848
TNTI GE 20/09/09 07:19:34.0 2.9 165 85.3495
TOLI2 GE 20/09/09 07:20:16.5 6.3 247 45.182133
BKB GE 20/09/09 07:21:27.2 10.8 244 5.4495
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200910082700
UGM GE 20/09/10 08:27:19.0 0.9 351 21.58078
SMRI GE 20/09/10 08:27:30.4 1.7 353 54.132877
JAGI GE 20/09/10 08:27:50.4 3.5 85 68.319756
BBJI GE 20/09/10 08:27:51.0 3.3 293 69.398796
PLAI GE 20/09/10 08:28:47.2 7.0 91 97.7505
TOLI2 GE 20/09/10 08:30:19.3 14.1 46 17.7495
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200910082701
UGM GE 20/09/10 08:27:19.0 0.8 350 21.58078
SMRI GE 20/09/10 08:27:30.4 1.7 353 54.132877
JAGI GE 20/09/10 08:27:50.4 3.5 86 68.319756
BBJI GE 20/09/10 08:27:51.0 3.2 293 69.398796
PLAI GE 20/09/10 08:28:47.2 7.0 91 39.372658
TOLI2 GE 20/09/10 08:30:19.3 14.1 46 17.7495
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200910103121
MSBI GE 20/09/10 10:34:17.8 12.4 122 7.3999
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200910103125
MSBI GE 20/09/10 10:34:17.8 12.0 131 7.3999
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200910203139
SOEI GE 20/09/10 20:36:57.6 24.1 264 25.569008
MMRI GE 20/09/10 20:37:13.4 26.1 267 4.3005
LUWI GE 20/09/10 20:37:19.2 26.6 284 4.8005
TOLI2 GE 20/09/10 20:37:40.0 29.2 287 6.3005
BBJI GE 20/09/10 20:39:18.9 40.6 268 4.3005
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200911030104
KTHA GE 20/09/11 03:01:31.4 1.6 171 40.05725
THERA GE 20/09/11 03:01:46.1 2.7 124 84.55
KARP GE 20/09/11 03:02:05.6 4.2 122 25.510687
MARCO GE 20/09/11 03:02:30.6 5.9 296 27.017687
TIRR GE 20/09/11 03:02:55.7 7.8 31 11.45
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200911073634
SALTA GE 20/09/11 07:37:03.1 0.9 212 79.421555
SNAA GE 20/09/11 07:46:15.9 59.4 161 16.5003
ACRG GE 20/09/11 07:47:23.7 70.3 74 54.11075
WIN GE 20/09/11 07:47:59.2 75.1 109 8.5
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200911073635
SALTA GE 20/09/11 07:37:03.1 1.0 230 79.421555
SNAA GE 20/09/11 07:46:15.9 59.2 161 16.5003
ACRG GE 20/09/11 07:47:23.7 70.0 74 54.11075
WIN GE 20/09/11 07:47:59.2 74.7 108 8.5
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200912024408
TOLI2 GE 20/09/12 02:52:01.1 42.4 213 7.1995
FAKI GE 20/09/12 02:52:03.5 42.6 195 3.5005
BNDI GE 20/09/12 02:52:22.2 44.7 197 49.84511
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200912024412
TOLI2 GE 20/09/12 02:52:01.1 41.9 212 7.1995
FAKI GE 20/09/12 02:52:03.5 42.2 194 3.5005
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200912130741
MNAI GE 20/09/12 13:08:27.8 2.8 299 93.4505
BBJI GE 20/09/12 13:08:24.2 2.8 128 78.8495
JAGI GE 20/09/12 13:09:54.7 9.1 108 19.185314
SOEI GE 20/09/12 13:12:02.9 19.1 103 29.120434
TNTI GE 20/09/12 13:12:40.9 22.9 74 5.9505
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200912202618
MMRI GE 20/09/12 20:27:49.9 6.4 260 69.1005
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200913102805
FAKI GE 20/09/13 10:31:51.4 16.1 282 9.9995
TNTI GE 20/09/13 10:33:00.6 21.9 288 0.0
SOEI GE 20/09/13 10:33:20.0 23.8 260 28.775858
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200913102806
FAKI GE 20/09/13 10:31:51.4 16.0 282 9.9995
TNTI GE 20/09/13 10:33:00.6 21.7 288 0.0
SOEI GE 20/09/13 10:33:20.0 23.7 260 28.775858
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200913170001
LUWI GE 20/09/13 17:00:55.1 3.6 77 94.8505
SANI GE 20/09/13 17:01:35.8 6.8 92 2.9005
SOEI GE 20/09/13 17:02:15.1 9.3 148 48.532444
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200913182854
LHMI GE 20/09/13 18:29:15.9 0.9 6 2.7005
GSI GE 20/09/13 18:29:39.3 3.1 166 49.34371
NPW GE 20/09/13 18:32:23.7 15.5 357 4.5
HALK GE 20/09/13 18:32:34.9 16.2 277 5.8
MALK GE 20/09/13 18:32:43.2 16.7 285 3.7505
TOLI2 GE 20/09/13 18:33:58.4 24.1 97 4.5505
PLAI GE 20/09/13 18:34:04.0 24.7 122 6.7995
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200913204200
TNTI GE 20/09/13 20:42:52.0 3.8 168 78.45316
PLAI GE 20/09/13 20:45:46.6 15.9 213 3.5505
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200915233404
NPW GE 20/09/15 23:36:57.8 12.2 132 36.442814
MALK GE 20/09/15 23:38:39.8 20.6 197 7.8005
ARPR GE 20/09/15 23:41:47.9 41.0 298 29.459126
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200916144835
ARPR GE 20/09/16 14:49:09.2 2.2 272 96.6
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200917030021
PSZ GE 20/09/17 03:10:44.6 62.7 39 17.85
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200917032724
FAKI GE 20/09/17 03:32:33.8 23.3 279 4.5495
LUWI GE 20/09/17 03:34:00.6 33.0 279 42.751923
TOLI2 GE 20/09/17 03:34:19.1 35.4 282 3.8495
PLAI GE 20/09/17 03:34:36.4 37.2 264 2.1505
BBJI GE 20/09/17 03:35:58.5 47.3 266 5.1495
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200917103226
SAUI GE 20/09/17 10:33:05.9 2.1 120 3.8495
BNDI GE 20/09/17 10:33:08.7 2.4 11 67.1495
FAKI GE 20/09/17 10:33:35.3 4.9 35 65.0505
MMRI GE 20/09/17 10:34:12.3 7.4 256 99.1505
LUWI GE 20/09/17 10:34:33.9 8.9 311 41.764202
PLAI GE 20/09/17 10:35:05.3 11.7 260 8.8005
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200917103227
SAUI GE 20/09/17 10:33:05.9 2.1 122 3.8495
BNDI GE 20/09/17 10:33:08.7 2.4 11 67.1495
FAKI GE 20/09/17 10:33:35.3 4.8 35 65.0505
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200917160109
GENI GE 20/09/17 16:01:19.3 0.5 204 17.018892
FAKI GE 20/09/17 16:03:08.2 8.1 265 20.733541
TNTI GE 20/09/17 16:04:19.4 13.3 283 13.1505
SOEI GE 20/09/17 16:05:14.7 17.7 244 14.9495
TOLI2 GE 20/09/17 16:05:38.5 19.8 279 8.4505
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200917233256
KTHA GE 20/09/17 23:33:10.9 0.7 71 22.9515
THERA GE 20/09/17 23:33:37.6 2.6 82 50.680157
KARP GE 20/09/17 23:33:58.7 4.0 96 98.1
MARCO GE 20/09/17 23:34:34.9 6.6 312 69.0871
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200926225027
THERA GE 20/09/26 22:51:29.0 3.8 166 84.95
KTHA GE 20/09/26 22:51:23.6 3.9 196 19.83725
MARCO GE 20/09/26 22:52:02.3 6.6 275 9.85
SALP GE 20/09/26 22:53:15.3 11.8 129 34.792515
UJAP GE 20/09/26 22:53:18.1 12.1 129 11.35
MSBI GE 20/09/26 22:53:27.1 12.5 131 73.34742
GHAJ GE 20/09/26 22:53:28.4 12.6 130 72.20458
EIL GE 20/09/26 22:53:36.5 13.5 137 87.0865
PABE GE 20/09/26 22:54:02.2 15.5 359 14.45
FLT1 GE 20/09/26 22:54:06.5 15.2 328 7.0
PBUR GE 20/09/26 22:54:15.1 16.1 355 9.4
VSU GE 20/09/26 22:54:38.3 18.5 4 22.912563
MTE GE 20/09/26 22:55:45.0 24.2 281 5.85
LODK GE 20/09/26 22:57:42.3 37.9 162 9.1
NPW GE 20/09/26 23:00:56.9 63.7 86 3.7
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200927094041
BOAB GE 20/09/27 09:42:12.6 6.1 107 31.48125
DAG GE 20/09/27 09:52:06.6 72.2 13 1.8
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200927172747
KARP GE 20/09/27 17:35:37.8 41.3 287 2.45
PABE GE 20/09/27 17:35:41.9 42.4 317 2.95
LODK GE 20/09/27 17:36:40.9 50.4 242 8.1
TNTI GE 20/09/27 17:37:31.5 56.8 115 5.5505
TOLI2 GE 20/09/27 17:36:56.7 51.8 121 51.68467
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200928205055
TOLI2 GE 20/09/28 20:55:42.1 21.4 181 6.2005
TNTI GE 20/09/28 20:55:51.6 22.5 164 9.542935
LUWI GE 20/09/28 20:56:06.8 23.5 176 54.367256
NPW GE 20/09/28 20:56:04.8 23.6 268 19.9175
SANI GE 20/09/28 20:56:17.2 24.9 169 11.603652
MMRI GE 20/09/28 20:57:11.3 31.1 178 6.2505
KARP GE 20/09/28 21:03:06.8 80.3 305 4.1
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200929040802
THERA GE 20/09/29 04:12:59.1 21.9 289 5.85
PABE GE 20/09/29 04:14:18.4 30.5 329 9.05
MORC GE 20/09/29 04:14:23.5 30.9 315 2.45
FLT1 GE 20/09/29 04:15:02.9 35.5 317 2.35
MSBI GE 20/09/29 04:11:26.7 13.7 272 23.445127
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200929175543
PLAI GE 20/09/29 17:56:11.6 1.8 325 33.393227
MTN AU 20/09/29 17:58:38.0 12.3 103 13.175
NPW GE 20/09/29 18:02:54.2 37.4 323 3.9
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200930043713
LDM MY 20/09/30 04:41:44.8 20.5 192 31.327827
TOLI2 GE 20/09/30 04:42:21.2 24.3 185 5.7505
TNTI GE 20/09/30 04:42:31.1 24.9 169 9.9005
NPW GE 20/09/30 04:42:31.6 25.1 263 36.029625
SANI GE 20/09/30 04:42:52.3 27.5 173 5.9002
BBJI GE 20/09/30 04:44:05.9 35.9 206 8.1995
MTN AU 20/09/30 04:44:30.7 39.0 167 2.225
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20200930221101
TNTI GE 20/09/30 22:12:55.4 8.8 177 29.205442
LDM MY 20/09/30 22:13:06.4 9.5 243 78.03007
TOLI2 GE 20/09/30 22:13:17.2 10.5 216 64.16646
LUWI GE 20/09/30 22:13:30.5 11.4 202 79.56005
SANI GE 20/09/30 22:13:28.7 11.7 185 16.5002
FAKI GE 20/09/30 22:14:00.5 13.6 157 28.191893
NPW GE 20/09/30 22:17:08.7 31.4 292 14.5
MMRI GE 20/09/30 22:15:10.3 18.8 195 1.9995
SOEI GE 20/09/30 22:15:16.6 19.5 188 26.01602
MTN AU 20/09/30 22:15:49.1 22.8 169 92.89881
SALP GE 20/09/30 22:23:31.1 86.4 302 2.9505
EIL GE 20/09/30 22:23:33.2 87.0 300 3.0
PLAI GE 20/09/30 22:15:27.7 20.6 207 21.745338
UJAP GE 20/09/30 22:23:30.1 86.2 302 17.497812
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201001103417
FAKI GE 20/10/01 10:38:37.4 18.2 282 20.238157
SAUI GE 20/10/01 10:38:42.6 18.6 266 5.2005
MTN AU 20/10/01 10:38:55.4 19.5 251 11.4
BNDI GE 20/10/01 10:38:59.9 20.2 276 7.9995
SANI GE 20/10/01 10:39:43.4 24.5 281 17.099699
SOEI GE 20/10/01 10:39:57.5 25.6 262 24.704185
MMRI GE 20/10/01 10:40:12.1 27.6 265 11.0495
LUWI GE 20/10/01 10:40:17.1 27.9 281 83.95138
PLAI GE 20/10/01 10:40:50.5 32.0 265 24.90157
LDM MY 20/10/01 10:41:09.8 33.8 290 86.3995
JAGI GE 20/10/01 10:41:22.1 35.6 265 11.695831
UGM GE 20/10/01 10:41:55.6 39.2 266 48.612873
BBJI GE 20/10/01 10:42:17.5 42.0 267 25.412184
GSI GE 20/10/01 10:43:43.4 53.0 277 13.634003
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201001110536
APE GE 20/10/01 11:06:03.9 1.2 284 2.35
THERA GE 20/10/01 11:06:04.0 1.2 250 1.9
KARP GE 20/10/01 11:06:04.1 1.3 171 26.84625
KTHA GE 20/10/01 11:06:26.0 3.1 261 44.51475
CSS GE 20/10/01 11:06:57.3 5.5 108 90.2
MARCO GE 20/10/01 11:07:47.5 9.4 295 47.634377
PSZ GE 20/10/01 11:08:28.8 12.3 337 2.7
MORC GE 20/10/01 11:08:58.8 14.6 335 4.9
STU GE 20/10/01 11:09:33.2 17.6 318 2.75
RUE GE 20/10/01 11:09:39.4 18.2 334 5.05
FALKS GE 20/10/01 11:09:42.6 18.6 328 7.5
PABE GE 20/10/01 11:09:45.8 18.8 355 2.15
FLT1 GE 20/10/01 11:09:47.3 19.0 330 3.7
WLF GE 20/10/01 11:09:56.3 19.7 317 5.0
MTE GE 20/10/01 11:11:05.5 27.0 288 3.2
LODK GE 20/10/01 11:12:09.4 34.3 165 1.7
HALK GE 20/10/01 11:15:13.8 57.7 108 1.45
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201001181536
SAUI GE 20/10/01 18:16:08.4 1.6 164 31.86688
BNDI GE 20/10/01 18:16:11.4 2.1 334 7.5005
FAKI GE 20/10/01 18:16:32.0 3.8 22 53.872635
TNTI GE 20/10/01 18:17:28.5 8.0 334 16.8495
PLAI GE 20/10/01 18:18:38.5 13.2 259 9.6998005
BBJI GE 20/10/01 18:20:31.0 23.0 266 5.2995
NPW GE 20/10/01 18:23:23.7 43.0 308 2.25
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201002233050
GENI GE 20/10/02 23:36:00.1 21.9 196 32.051212
FAKI GE 20/10/02 23:36:33.7 25.3 214 5.9495
SANI GE 20/10/02 23:37:02.0 28.4 226 3.2995
LDM MY 20/10/02 23:37:16.4 29.9 247 13.5995
TOLI2 GE 20/10/02 23:37:16.8 30.2 238 2.4505
MTN AU 20/10/02 23:37:53.4 34.6 206 2.425
SOEI GE 20/10/02 23:38:00.4 35.5 219 13.7505
MMRI GE 20/10/02 23:38:04.7 35.9 223 3.5505
PLAI GE 20/10/02 23:38:31.8 39.0 228 4.7498
JAGI GE 20/10/02 23:38:51.2 41.4 232 3.5505
GSI GE 20/10/02 23:40:04.2 50.4 256 4.211637
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201003032441
TNTI GE 20/10/03 03:26:30.4 7.0 176 35.302998
TOLI2 GE 20/10/03 03:26:56.6 9.0 223 99.8505
SOEI GE 20/10/03 03:28:52.4 17.7 189 6.6995
PLAI GE 20/10/03 03:29:03.6 18.9 209 4.8498
JAGI GE 20/10/03 03:29:24.1 20.6 218 7.0505
MTN AU 20/10/03 03:29:30.1 21.0 168 3.825
BBJI GE 20/10/03 03:30:05.7 24.5 232 4.2005
GSI GE 20/10/03 03:30:56.2 29.9 259 26.77447
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201003050336
LDM MY 20/10/03 05:05:53.0 8.6 196 30.130375
TOLI2 GE 20/10/03 05:06:39.6 12.4 180 6.0005
LUWI GE 20/10/03 05:07:11.7 14.7 172 5.0505
SANI GE 20/10/03 05:07:25.2 16.4 161 4.3005
PLAI GE 20/10/03 05:08:32.8 22.5 188 3.5498002
JAGI GE 20/10/03 05:08:36.8 22.9 197 3.7995
GSI GE 20/10/03 05:09:03.9 26.0 244 2.4495
EIL GE 20/10/03 05:15:38.7 79.8 298 2.55
KIBK GE 20/10/03 05:15:58.6 83.5 266 2.3495
KARP GE 20/10/03 05:16:04.7 85.1 305 2.45
BKNI GE 20/10/03 05:08:43.7 23.6 238 1.3005
SMRI GE 20/10/03 05:08:38.9 23.0 207 95.1998
NPW GE 20/10/03 05:08:50.9 24.4 288 75.6
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201003093144
TNTI GE 20/10/03 09:38:19.8 35.1 204 5.4505
LDM MY 20/10/03 09:38:23.5 35.2 222 21.71091
GENI GE 20/10/03 09:38:29.8 36.0 182 10.6005
FAKI GE 20/10/03 09:38:40.2 37.3 195 5.6495
TOLI2 GE 20/10/03 09:38:39.7 37.5 215 5.8495
SANI GE 20/10/03 09:38:44.8 38.2 205 5.3495
LUWI GE 20/10/03 09:38:49.3 38.5 210 10.6495
NPW GE 20/10/03 09:39:20.2 42.1 263 16.495
MMRI GE 20/10/03 09:39:47.9 45.7 207 53.960552
SOEI GE 20/10/03 09:39:49.7 46.1 203 24.518122
MTN AU 20/10/03 09:39:59.0 47.2 193 4.25
PLAI GE 20/10/03 09:40:01.6 47.7 212 6.3498
JAGI GE 20/10/03 09:40:15.8 49.1 216 13.6005
UGM GE 20/10/03 09:40:24.4 50.5 221 2.9505
GSI GE 20/10/03 09:40:35.0 51.9 241 9.9505
DAG GE 20/10/03 09:42:34.6 69.1 355 5.6
VSU GE 20/10/03 09:42:59.4 73.2 330 32.055218
PABE GE 20/10/03 09:43:17.3 76.2 329 18.25
ARPR GE 20/10/03 09:43:29.7 78.2 309 29.9
TIRR GE 20/10/03 09:43:44.6 81.1 318 31.181032
MORC GE 20/10/03 09:43:54.4 83.0 327 27.673626
PSZ GE 20/10/03 09:43:55.4 83.2 325 28.259407
FLT1 GE 20/10/03 09:43:58.0 83.8 332 22.513124
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201003122521
NPW GE 20/10/03 12:32:58.6 32.1 258 6.95
ARPR GE 20/10/03 12:37:57.9 72.0 305 4.7
EIL GE 20/10/03 12:38:35.9 78.8 298 3.8
PSZ GE 20/10/03 12:38:37.9 79.2 320 5.45
UJAP GE 20/10/03 12:38:28.4 77.3 300 57.2725
GHAJ GE 20/10/03 12:38:29.0 77.5 299 2.6505
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201003162956
FAKI GE 20/10/03 16:34:20.9 19.3 282 31.759516
SAUI GE 20/10/03 16:34:30.7 19.6 267 2.8995
MTN AU 20/10/03 16:34:33.2 20.4 253 4.25
BNDI GE 20/10/03 16:34:47.1 21.2 276 23.803467
TNTI GE 20/10/03 16:35:22.1 25.0 288 16.0505
SANI GE 20/10/03 16:35:26.9 25.5 281 14.1005
SOEI GE 20/10/03 16:35:38.1 26.6 263 12.3995
MMRI GE 20/10/03 16:35:53.8 28.6 265 10.8005
LUWI GE 20/10/03 16:35:57.1 28.9 281 22.368744
TOLI2 GE 20/10/03 16:36:17.2 31.4 284 5.0505
PLAI GE 20/10/03 16:36:32.5 33.0 265 10.7498
JAGI GE 20/10/03 16:37:01.8 36.6 266 10.4505
SMRI GE 20/10/03 16:37:37.9 40.3 268 2.8998
BBJI GE 20/10/03 16:37:57.6 43.0 267 10.0495
GSI GE 20/10/03 16:39:24.2 54.1 277 13.4995
NPW GE 20/10/03 16:40:09.6 60.5 298 25.37525
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201004181704
LDM MY 20/10/04 18:19:01.7 8.9 195 15.8505
TOLI2 GE 20/10/04 18:19:49.2 12.6 180 9.9505
TNTI GE 20/10/04 18:20:11.5 14.5 153 8.8995
LUWI GE 20/10/04 18:20:19.1 14.9 172 28.292871
BKB GE 20/10/04 18:20:28.4 15.3 195 11.613172
SANI GE 20/10/04 18:20:35.8 16.6 162 4.4995
FAKI GE 20/10/04 18:21:17.9 20.1 145 5.6995
BNDI GE 20/10/04 18:21:21.6 20.3 153 17.63723
MMRI GE 20/10/04 18:21:40.8 22.4 176 7.8995
PLAI GE 20/10/04 18:21:44.3 22.8 188 3.8498
PMBI GE 20/10/04 18:21:48.9 23.1 225 2.7998002
JAGI GE 20/10/04 18:21:48.6 23.2 197 4.1495
SMRI GE 20/10/04 18:21:50.0 23.2 207 3.2498
SOEI GE 20/10/04 18:21:53.2 23.7 172 10.9005
BKNI GE 20/10/04 18:21:53.9 23.8 237 2.9005
UGM GE 20/10/04 18:21:57.3 23.9 206 7.7495
NPW GE 20/10/04 18:22:00.7 24.4 288 3.4
BBJI GE 20/10/04 18:22:04.0 24.9 212 5.1495
LHMI GE 20/10/04 18:22:07.7 25.0 252 3.9495
MNAI GE 20/10/04 18:22:09.9 25.3 226 5.0995
GENI GE 20/10/04 18:22:10.6 25.2 129 75.204605
GSI GE 20/10/04 18:22:14.6 26.2 244 2.6505
PBA IN 20/10/04 18:22:28.2 27.5 269 3.7
MALK GE 20/10/04 18:24:16.1 39.9 267 2.0005
HALK GE 20/10/04 18:24:19.0 40.2 263 2.1
SBV GE 20/10/04 18:28:23.6 75.3 252 3.0
ARPR GE 20/10/04 18:28:27.1 75.6 307 4.4
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201005030744
PLAI GE 20/10/05 03:08:19.0 2.0 307 28.904116
MMRI GE 20/10/05 03:08:34.1 3.1 64 68.582375
SANI GE 20/10/05 03:10:12.6 10.3 40 7.6505
TOLI2 GE 20/10/05 03:10:25.8 11.2 7 73.1505
MTN AU 20/10/05 03:10:32.3 11.8 105 10.925
BBJI GE 20/10/05 03:10:34.9 11.9 282 17.640974
TNTI GE 20/10/05 03:10:53.4 13.4 37 10.3005
FAKI GE 20/10/05 03:11:07.3 14.6 62 7.8995
NPW GE 20/10/05 03:14:53.8 37.6 322 2.9
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201005234323
NPW GE 20/10/05 23:48:02.2 20.9 130 11.25
ARPR GE 20/10/05 23:49:58.1 32.8 290 11.35
KARP GE 20/10/05 23:51:15.2 42.2 287 7.3
PABE GE 20/10/05 23:51:22.5 43.0 317 6.05
APE GE 20/10/05 23:51:22.4 43.1 290 8.95
THERA GE 20/10/05 23:51:23.1 43.3 289 22.116095
KTHA GE 20/10/05 23:51:38.8 45.2 289 10.0
MORC GE 20/10/05 23:51:53.4 46.9 309 5.65
FLT1 GE 20/10/05 23:52:20.8 50.5 313 5.9
TOLI2 GE 20/10/05 23:52:23.6 51.0 121 6.5495
LODK GE 20/10/05 23:52:24.3 51.1 243 7.7
KIBK GE 20/10/05 23:52:39.3 53.3 235 16.5495
LUWI GE 20/10/05 23:52:49.5 54.0 122 7.4995
TNTI GE 20/10/05 23:52:59.8 56.0 116 67.8005
SANI GE 20/10/05 23:53:05.0 57.0 120 6.2505
DAG GE 20/10/05 23:53:18.8 58.4 345 12.6
SOEI GE 20/10/05 23:53:34.3 61.4 127 11.4005
FAKI GE 20/10/05 23:53:45.6 62.1 116 6.3505
VOI GE 20/10/05 23:53:53.8 64.2 214 1.75
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201006230121
TNTI GE 20/10/06 23:08:30.1 35.0 197 4.5505
FAKI GE 20/10/06 23:08:51.3 37.7 188 2.8495
LUWI GE 20/10/06 23:08:54.6 38.0 204 4.7995
SANI GE 20/10/06 23:08:53.8 38.0 198 2.9505
BNDI GE 20/10/06 23:09:07.8 39.6 191 95.96391
SOEI GE 20/10/06 23:09:58.7 45.9 198 6.6505
MTN AU 20/10/06 23:10:10.7 47.7 188 4.1
DAG GE 20/10/06 23:12:35.5 67.7 354 1.8
PABE GE 20/10/06 23:13:12.3 73.6 327 1.9
ARPR GE 20/10/06 23:13:22.3 75.0 307 2.25
MORC GE 20/10/06 23:13:49.5 80.3 325 1.55
PSZ GE 20/10/06 23:13:50.5 80.4 323 1.9
CSS GE 20/10/06 23:13:51.5 80.6 306 3.55
GHAJ GE 20/10/06 23:13:55.3 81.2 302 1.5745
EIL GE 20/10/06 23:14:01.7 82.6 301 2.85
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201007065011
MMRI GE 20/10/07 06:50:47.2 2.2 30 34.705593
SOEI GE 20/10/07 06:51:03.9 3.2 76 75.61737
PLAI GE 20/10/07 06:51:08.2 3.7 297 54.27623
JAGI GE 20/10/07 06:51:54.2 7.2 286 99.9495
LUWI GE 20/10/07 06:52:30.8 9.7 10 13.4995
SANI GE 20/10/07 06:52:32.0 9.8 30 4.4505
FAKI GE 20/10/07 06:53:20.6 13.4 56 9.0005
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201007182433
BOAB GE 20/10/07 18:29:23.8 21.5 258 32.20375
SUMG GE 20/10/07 18:34:15.9 56.6 9 3.3
DAG GE 20/10/07 18:34:59.3 63.2 11 2.3
ACRG GE 20/10/07 18:35:04.2 63.5 93 2.2
FLT1 GE 20/10/07 18:35:26.3 67.0 40 1.65
FALKS GE 20/10/07 18:35:26.8 67.0 41 2.2
MORC GE 20/10/07 18:35:51.9 71.1 42 2.55
PSZ GE 20/10/07 18:36:03.2 72.9 44 7.25
PABE GE 20/10/07 18:36:10.3 74.3 36 2.0
VSU GE 20/10/07 18:36:14.7 75.3 33 1.75
CSS GE 20/10/07 18:37:13.8 85.7 55 3.5
ARPR GE 20/10/07 18:37:25.3 88.0 49 2.8
MSBI GE 20/10/07 18:37:28.1 88.5 57 2.2
EIL GE 20/10/07 18:37:28.9 88.7 59 1.7
SNAA GE 20/10/07 18:38:10.2 98.5 164 2.5003
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201008073511
FAKI GE 20/10/08 07:38:48.3 14.9 282 15.6005
SAUI GE 20/10/08 07:38:59.6 15.5 263 8.3495
BNDI GE 20/10/08 07:39:15.9 16.9 275 53.224266
MTN AU 20/10/08 07:39:14.5 16.8 246 32.014023
TNTI GE 20/10/08 07:39:57.6 20.6 289 10.4505
SANI GE 20/10/08 07:40:01.9 21.2 281 11.0495
MMRI GE 20/10/08 07:40:36.2 24.5 263 97.21523
LUWI GE 20/10/08 07:40:37.6 24.5 281 6.7005
TOLI2 GE 20/10/08 07:40:57.8 27.0 285 9.1505
BKB GE 20/10/08 07:41:30.4 30.3 279 5.8005
BBJI GE 20/10/08 07:42:41.4 38.9 266 11.7995
GSI GE 20/10/08 07:44:08.9 49.7 277 4.8005
NPW GE 20/10/08 07:44:58.1 56.2 299 5.35
HALK GE 20/10/08 07:46:13.7 67.1 279 91.57531
VOI GE 20/10/08 07:48:51.2 96.9 247 6.782
SNAA GE 20/10/08 07:49:02.8 99.6 189 86.7003
SBV GE 20/10/08 07:48:44.0 95.2 256 16.35
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201008155846
TNTI GE 20/10/08 16:04:09.2 24.4 286 48.278095
SOEI GE 20/10/08 16:04:29.1 26.4 261 95.79065
PLAI GE 20/10/08 16:05:23.1 32.8 263 20.359661
JAGI GE 20/10/08 16:05:53.8 36.3 264 19.020884
BBJI GE 20/10/08 16:06:48.7 42.8 266 21.437365
TOLI2 GE 20/10/08 16:05:09.2 30.8 283 21.672422
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201008231407
FAKI GE 20/10/08 23:17:55.3 16.4 282 8.3005
SAUI GE 20/10/08 23:18:07.1 17.0 264 3.3005
BNDI GE 20/10/08 23:18:23.5 18.5 275 5.0005
TNTI GE 20/10/08 23:19:05.7 22.2 288 4.2005
SANI GE 20/10/08 23:19:06.1 22.7 280 8.0995
MMRI GE 20/10/08 23:19:40.5 26.0 263 4.0505
LUWI GE 20/10/08 23:19:41.2 26.1 281 4.1005
TOLI2 GE 20/10/08 23:20:01.9 28.5 284 5.5005
BBJI GE 20/10/08 23:21:45.9 40.4 266 2.8505
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201009025722
SOEI GE 20/10/09 02:58:25.3 4.1 158 62.105835
SANI GE 20/10/09 02:58:37.6 5.1 40 75.3005
TOLI2 GE 20/10/09 02:59:07.6 7.4 345 31.458633
TNTI GE 20/10/09 02:59:21.3 8.2 35 13.2505
JAGI GE 20/10/09 02:59:31.1 8.9 253 20.326109
FAKI GE 20/10/09 02:59:47.5 10.0 72 27.251883
AUDHS S1 20/10/09 02:59:48.2 10.3 129 6.8
FITZ AU 20/10/09 03:00:19.2 12.4 167 20.12547
SMRI GE 20/10/09 03:00:21.1 12.3 264 21.799286
SBM MY 20/10/09 03:00:35.3 13.5 309 15.5505
BBJI GE 20/10/09 03:00:54.7 15.0 264 30.691748
UBIN MS 20/10/09 03:01:55.9 20.2 291 8.9995
KOM MY 20/10/09 03:01:58.1 20.4 292 9.6005
IPM MY 20/10/09 03:02:35.7 24.1 295 7.1005
PSI PS 20/10/09 03:02:46.8 25.3 289 8.3003
BKNI GE 20/10/09 03:02:25.5 22.6 286 0.0
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201009052828
LUWI GE 20/10/09 05:29:53.6 5.7 341 4.0005
 Stat  Net Date  Time Dist  Az Tdur untuk evid 20201010175538
WB7 AU 20/10/10 17:58:45.3 2.5 145 6.625
WB1 AU 20/10/10 17:58:47.2 2.6 147 7.8
WC4 AU 20/10/10 17:58:48.0 2.6 147 6.7
WR1 AU 20/10/10 17:58:47.1 2.6 147 3.85
WB2 AU 20/10/10 17:58:47.2 2.6 147 4.0
JAGI GE 20/10/10 18:02:41.7 20.4 295 22.871372
BBJI GE 20/10/10 18:03:44.8 26.6 290 91.3505
WC3 AU 20/10/10 17:58:47.8 2.6 146 1.1
WB6 AU 20/10/10 17:58:45.4 2.5 145 3.425
WB5 AU 20/10/10 17:58:45.5 2.6 146 4.1
WC1 AU 20/10/10 17:58:46.7 2.6 147 3.8
WB3 AU 20/10/10 17:58:47.6 2.6 146 3.35
WR4 AU 20/10/10 17:58:49.3 2.6 146 4.125
WR6 AU 20/10/10 17:58:52.1 2.7 145 3.725
WR9 AU 20/10/10 17:58:49.0 2.7 144 4.875
WB8 AU 20/10/10 17:58:45.3 2.5 144 1.0';
}
