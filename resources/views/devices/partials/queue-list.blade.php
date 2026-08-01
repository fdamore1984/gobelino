@if ($commands->isEmpty())
    <p class="px-4 py-4 text-xs text-gray-400">No commands sent yet.</p>
@else
    <ul class="divide-y">
        @foreach ($commands as $command)
            <li class="px-4 py-3 flex items-center justify-between gap-2">
                <div>
                    <p class="text-gray-800 capitalize">{{ str_replace('_', ' ', $command->type) }}</p>
                    <p class="text-[11px] text-gray-400">{{ $command->created_at->diffForHumans() }}</p>
                </div>
                @php
                    $statusMap = [
                        'pending' => ['In coda', 'bg-gray-100 text-gray-600'],
                        'sent' => ['Consegnato', 'bg-yellow-100 text-yellow-800'],
                        'acked' => ['Eseguito', 'bg-green-100 text-green-800'],
                        'failed' => ['Fallito', 'bg-red-100 text-red-700'],
                    ];
                    [$label, $classes] = $statusMap[$command->status] ?? [$command->status, 'bg-gray-100 text-gray-600'];
                @endphp
                <span class="text-[10px] px-2 py-0.5 rounded-full whitespace-nowrap {{ $classes }}">{{ $label }}</span>
            </li>
        @endforeach
    </ul>
@endif
