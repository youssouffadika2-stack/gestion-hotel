import './charts';

// Quand une action Livewire est refusée ou échoue, on l'explique à l'utilisateur (au lieu d'un clic « sans effet »).
document.addEventListener('livewire:init', () => {
    const say = (message) => window.dispatchEvent(new CustomEvent('toast', { detail: { message, type: 'error' } }));
    const messages = {
        401: 'Votre session a expiré : reconnectez-vous.',
        403: "Action non autorisée pour votre rôle. Demandez l'accès à votre administrateur.",
        404: 'Élément introuvable (il a peut-être été supprimé). Actualisez la page.',
        419: 'Votre session a expiré : actualisez la page (touche F5) puis recommencez.',
        429: 'Trop de requêtes : patientez un instant puis recommencez.',
    };

    window.Livewire.hook('request', ({ respond, fail }) => {
        let expired = false;

        // Session perdue : le serveur redirige vers la page de connexion au lieu de répondre à Livewire.
        respond(({ response }) => {
            if (response.redirected && /\/login/.test(response.url)) {
                expired = true;
                say('Votre session a expiré : reconnexion en cours…');
                setTimeout(() => window.location.assign(response.url), 1500);
            }
        });

        fail(({ status, preventDefault }) => {
            if (expired) {
                preventDefault();
            } else if (messages[status]) {
                preventDefault();
                say(messages[status]);
            }
        });
    });
});
