<div class="card">
    <div class="text-center" wire:loading>
        Loading data...
    </div>
    <div class="table-responsive">
        <table class="table" wire:loading.remove>
            <thead>
            <tr>
                <th>No</th>
                @foreach($keys as $k)
                    <th>{{ ucwords($k) }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($data as $_)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    @foreach($keys as $k)
                        <td>{{ $_[$k] }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
