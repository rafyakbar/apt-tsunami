<?php

namespace App\Http\Livewire\Components\Charts;

use App\Charts\TdurAzimuth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class RuptureAzimuth extends Component
{
    public function render()
    {
        $data = json_decode(Storage::disk('public')->get('data.json'), true);
        $data = collect($data);

        $chart = new TdurAzimuth();
        $chart->title('Grafik Hubungan Durasi Rupture (Tdur) dengan Azimuth');
        $chart->load(route('chart'));

        $dates = $data->keys();

        return view('livewire.components.charts.rupture-azimuth', [
            'chart' => $chart,
            'data' => $data,
            'dates' => $dates
        ]);
    }
}
