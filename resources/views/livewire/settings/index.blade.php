<div class="mx-auto max-w-2xl">
    <x-ui.page-header title="Paramètres de l'hôtel" subtitle="Informations générales de l'établissement." />

    <x-ui.card>
        <form wire:submit="save" class="space-y-4">
            <x-ui.field label="Nom de l'hôtel" name="name"><input type="text" wire:model="name" class="hp-input" autocomplete="off"></x-ui.field>
            <x-ui.field label="Ville" name="city"><input type="text" wire:model="city" class="hp-input" autocomplete="off"></x-ui.field>
            <x-ui.field label="Devise" name="currency" hint="La devise est fixée sur le franc CFA (XOF)."><input type="text" value="FCFA (XOF)" class="hp-input" disabled></x-ui.field>
            <div class="flex justify-end">
                <button type="submit" class="hp-btn-teal" wire:loading.attr="disabled">Enregistrer</button>
            </div>
        </form>
    </x-ui.card>
</div>
