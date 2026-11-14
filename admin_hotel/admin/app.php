<?php
// Administration générale : session, authentification, mise en page
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
session_name('hotel_admin');
session_start();
csrf_check();

function require_login(): array
{
    if (empty($_SESSION['admin'])) {
        redirect('login.php');
    }
    return $_SESSION['admin'];
}

function header_html(string $title, string $active = ''): void
{
    $u = $_SESSION['admin'] ?? null;
    $flash = flash();
    $menu = [
        'index.php' => 'Tableau de bord',
        'hotels.php' => 'Hôtels',
        'catalogues.php' => 'Catalogues',
        'photos.php' => 'Photos (galerie)',
        'affiche.php' => 'Créer une affiche pub',
        'publicites.php' => 'Publicités (paiements)',
        'reservations.php' => 'Réservations',
        'parametres.php' => 'Mon entreprise',
        'motdepasse.php' => 'Mon mot de passe',
    ];
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' - Admin Hôtels</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">';
    echo '<link href="../assets/admin.css" rel="stylesheet"></head><body>';
    echo '<nav class="navbar navbar-dark bg-dark px-3"><span class="navbar-brand"><i class="bi bi-building me-1"></i>Admin Hôtels</span>';
    if ($u) {
        echo '<span class="text-white"><a class="text-white me-3" href="' . e(GESTION_BASE_URL) . '" target="_blank">Voir le site public</a>' . e($u['nom']) . ' · <a class="text-white" href="logout.php">Déconnexion</a></span>';
    }
    echo '</nav><div class="container-fluid"><div class="row">';
    if ($u) {
        echo '<div class="col-md-2 sidebar py-3"><div class="list-group">';
        foreach ($menu as $file => $label) {
            echo '<a class="list-group-item list-group-item-action' . ($file === $active ? ' active' : '') . '" href="' . $file . '">' . $label . '</a>';
        }
        echo '</div></div>';
    }
    echo '<main class="' . ($u ? 'col-md-10' : 'col-12') . ' p-4">';
    if ($flash) {
        echo '<div class="alert alert-' . e($flash['type']) . '">' . e($flash['msg']) . '</div>';
    }
}

function footer_html(): void
{
    echo '</main></div></div></body></html>';
}

const STATUTS_HOTEL = ['en_attente' => ['En attente', 'warning'], 'actif' => ['Actif', 'success'], 'suspendu' => ['Suspendu', 'secondary']];
const STATUTS_RESA = ['en_attente' => ['En attente', 'warning'], 'confirmee' => ['Confirmée', 'success'], 'annulee' => ['Annulée', 'danger']];

function badge(array $map, string $key): string
{
    [$label, $color] = $map[$key] ?? [$key, 'light'];
    return '<span class="badge text-bg-' . $color . '">' . e($label) . '</span>';
}
