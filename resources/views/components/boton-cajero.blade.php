<button {{ $attributes->merge(['class' => 'bg-slate-700 text-white py-2 px-4 rounded shadow hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed']) }}>
    {{$slot}}
</button>