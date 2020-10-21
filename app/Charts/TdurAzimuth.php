<?php

namespace App\Charts;

use ConsoleTVs\Charts\Classes\Chartjs\Chart;

class TdurAzimuth extends Chart
{
    /**
     * Initializes the chart.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->options([
            'scales' => [
                'xAxes' => [[
                    'scaleLabel' => [
                        'display' => true,
                        'labelString' => 'Azimuth (o)'
                    ], 'type' => 'linear',
                    'display' => true
                ]], 'yAxes' => [[
                    'scaleLabel' => [
                        'display' => true,
                        'labelString' => 'Durasi Rupture (s)'
                    ]
                ]]
            ], 'elements' => [
                'line' => [
                    'tension' => 0
                ]
            ]
        ]);
    }
}
