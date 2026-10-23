@props([
    'photo',
])

<figure {{ $attributes->class('relative') }}>
    <a href="{{ $photo->url() }}" target="_blank" rel="noopener" class="block aspect-square overflow-hidden border-2 border-line bg-neutral-200">
        <img src="{{ $photo->thumbUrl() }}" alt="{{ $photo->caption ?: $photo->kind->label() }}" loading="lazy" class="size-full object-cover">
    </a>
    <span class="absolute left-2 top-2 inline-flex bg-ink px-2 py-1 text-micro uppercase text-bg">{{ $photo->kind->label() }}</span>
    @if ($photo->caption)
        <figcaption class="mt-1 truncate text-label text-ink/70">{{ $photo->caption }}</figcaption>
    @endif
    {{ $slot }}
</figure>
