<div>
    <div class="card">
        <select id="d" class="form-control" wire:change="$emit('refresh-chart', $('#d').val())" wire:ignore>
            <option value="{{ route('chart', ['date' => '-']) }}">Data percobaan</option>
            @foreach($dates as $date)
                @if($date != '-')
                    <option value="{{ route('chart', ['date' => $date]) }}" @if($loop->first) selected @endif>
                        Data pada tanggal {{ $date }}
                    </option>
                @endif
            @endforeach
        </select>
        <div class="card-body" wire:ignore>
            {!! $chart->container() !!}
        </div>
        <button class="btn btn-primary" wire:click="$emit('refresh-chart', $('#d').val())">
            Perbarui Grafik
        </button>
    </div>
</div>

@push('js')
    {!! $chart->script() !!}
    <script>
        window.livewire.on('refresh-chart', data => {
            async function ref(){
                await {{ $chart->id }}_refresh(data)
                Livewire.emitTo('components.data-preview', 'changeDate', $('#d').val())
            }
            ref()
        })
    </script>
@endpush
