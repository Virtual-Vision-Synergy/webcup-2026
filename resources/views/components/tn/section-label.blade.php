@props(['as' => 'p'])

<{{ $as }} {{ $attributes->class('tn-label text-ink-2') }}>{{ $slot }}</{{ $as }}>
