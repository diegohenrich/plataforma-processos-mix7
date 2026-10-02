@props(['points'])

@php($renderedPoints = collect($points)->filter(fn ($point) => is_array($point) && is_numeric($point['x'] ?? null) && is_numeric($point['y'] ?? null))->map(fn ($point) => number_format((float) $point['x'], 1, '.', '').','.number_format((float) $point['y'], 1, '.', ''))->implode(' '))
@if (count($points) >= 2 && $renderedPoints !== '')
    <svg class="drawing-preview" viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="Rabisco feito pelo cliente sobre a prévia"><polyline points="{{ $renderedPoints }}"></polyline></svg>
@endif
