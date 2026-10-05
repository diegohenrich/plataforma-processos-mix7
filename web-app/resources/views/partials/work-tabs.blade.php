@php
    $workView = $workView ?? request()->query('view', 'board');
    $workTabs = [
        ['key' => 'tasks', 'label' => 'Tarefas', 'url' => route('demands.index', ['view' => 'tasks'])],
    ];
@endphp
<nav class="workspace-tabs" aria-label="Seções do espaço de demandas">
    @foreach ($workTabs as $tab)
        <a href="{{ $tab['url'] }}" @if ($workView === $tab['key']) aria-current="page" @endif>{{ $tab['label'] }}</a>
    @endforeach
</nav>
