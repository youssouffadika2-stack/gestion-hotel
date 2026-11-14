@php
    // Sous Apache le site vit dans un sous-dossier (http://localhost/gestion-hotel/public) : les adresses de Livewire
    // (script + actions des boutons) doivent contenir ce dossier, sinon chaque clic échoue en 404.
    $base = request()->getBasePath();
    $html = \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts();
    if ($base !== '') {
        $html = str_replace(['src="/livewire/', 'data-update-uri="/livewire/'], ['src="'.$base.'/livewire/', 'data-update-uri="'.$base.'/livewire/'], $html);
    }
@endphp
{!! $html !!}
