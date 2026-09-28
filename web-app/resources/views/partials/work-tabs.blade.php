@php
    $workView = $workView ?? request()->query('view', 'board');
    $workTabs = [
        ['key' => 'board', 'label' => 'Quadro', 'url' => route('demands.index', ['view' => 'board'])],
        ['key' => 'list', 'label' => 'Lista', 'url' => route('demands.index', ['view' => 'list'])],
        ['key' => 'tasks', 'label' => 'Tarefas', 'url' => route('demands.index', ['view' => 'tasks'])],
    ];
@endphp
<nav class="workspace-tabs" aria-label="Visualizações e tarefas das demandas">
    @foreach ($workTabs as $tab)
        <a href="{{ $tab['url'] }}" @if ($workView === $tab['key']) aria-current="page" @endif>{{ $tab['label'] }}</a>
    @endforeach
    @can('create', App\Models\Demand::class)
        <a class="workspace-settings" href="{{ route('approval-modules.index') }}" @if ($workView === 'settings') aria-current="page" @endif>Configurar tipos de aprovação</a>
    @endcan
</nav>
