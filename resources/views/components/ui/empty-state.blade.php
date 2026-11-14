@props(['icon' => 'inbox', 'message'])

<div class="flex flex-col items-center justify-center gap-2 px-4 py-10 text-center">
    <span class="flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <x-ui.icon :name="$icon" class="size-5" />
    </span>
    <p class="text-sm text-slate-500">{{ $message }}</p>
</div>
