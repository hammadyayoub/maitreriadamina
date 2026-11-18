<?php
declare(strict_types=1);

require __DIR__ . '/includes/store.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

if (empty($_SESSION['jeton'])) {
    $_SESSION['jeton'] = bin2hex(random_bytes(16));
}

function connecte(): bool
{
    return !empty($_SESSION['connecte']);
}

function jeton_valide(): bool
{
    return isset($_POST['jeton'], $_SESSION['jeton']) && hash_equals($_SESSION['jeton'], (string) $_POST['jeton']);
}

$erreur = '';
$message = '';

if (isset($_GET['ok'])) {
    $textes = [
        'ajoute' => 'La publication a été ajoutée.',
        'modifie' => 'La publication a été modifiée.',
        'supprime' => 'La publication a été supprimée.',
    ];
    $message = $textes[(string) $_GET['ok']] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!jeton_valide()) {
        $erreur = 'La demande a expiré. Rechargez la page.';
    } elseif (isset($_POST['deconnexion'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: admin.php');
        exit;
    } elseif (!connecte()) {
        $_SESSION['essais'] = (int) ($_SESSION['essais'] ?? 0);
        $bloque = $_SESSION['essais'] >= 5 && time() - (int) ($_SESSION['blocage'] ?? 0) < 300;
        if ($bloque) {
            $erreur = 'Trop de tentatives. Réessayez dans quelques minutes.';
        } elseif (mot_de_passe_valide((string) ($_POST['mot_de_passe'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['connecte'] = true;
            $_SESSION['essais'] = 0;
            $_SESSION['jeton'] = bin2hex(random_bytes(16));
            header('Location: admin.php');
            exit;
        } else {
            $_SESSION['essais']++;
            if ($_SESSION['essais'] >= 5) {
                $_SESSION['blocage'] = time();
            }
            $erreur = 'Mot de passe incorrect.';
        }
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $publications = publications_lire();

        if ($action === 'supprimer') {
            $id = (string) ($_POST['id'] ?? '');
            $publications = array_values(array_filter(
                $publications,
                static fn (array $item): bool => ($item['id'] ?? '') !== $id
            ));
            if (!publications_enregistrer($publications)) {
                $erreur = 'Enregistrement impossible. Le dossier data doit être accessible en écriture.';
            } else {
                header('Location: admin.php?ok=supprime');
                exit;
            }
        } else {
            $propre = publication_nettoyer($_POST);
            if ($propre === []) {
                $erreur = 'Vérifiez le titre, le résumé et, s’il est renseigné, le lien (http ou https).';
            } else {
                $id = (string) ($_POST['id'] ?? '');
                $trouve = false;
                foreach ($publications as &$item) {
                    if ($id !== '' && ($item['id'] ?? '') === $id) {
                        $item = array_merge($item, $propre);
                        $trouve = true;
                        break;
                    }
                }
                unset($item);
                if (!$trouve) {
                    $propre['id'] = bin2hex(random_bytes(8));
                    $propre['cree'] = gmdate('c');
                    $publications[] = $propre;
                }
                if (!publications_enregistrer($publications)) {
                    $erreur = 'Enregistrement impossible. Le dossier data doit être accessible en écriture.';
                } else {
                    header('Location: admin.php?ok=' . ($trouve ? 'modifie' : 'ajoute'));
                    exit;
                }
            }
        }
    }
}

$edition = null;
if (connecte() && isset($_GET['modifier'])) {
    foreach (publications_lire() as $item) {
        if (($item['id'] ?? '') === (string) $_GET['modifier']) {
            $edition = $item;
            break;
        }
    }
}

function e(string $valeur): string
{
    return htmlspecialchars($valeur, ENT_QUOTES, 'UTF-8');
}

$jeton = e((string) ($_SESSION['jeton'] ?? ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Publications | Maître Amina Riad</title>
  <link rel="icon" href="favicon.ico" sizes="any">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
  <style>
    :root { --main: #fcbb68; --dark: #24285c; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Playfair Display", serif; background: #f6f5f2; color: #333; }
    header, main { width: min(760px, calc(100% - 2rem)); margin: 0 auto; }
    header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.25rem 0; }
    h1 { margin: 0; color: var(--dark); font-size: 1.6rem; }
    a { color: var(--dark); }
    .panel { background: #fff; border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; }
    label { display: block; margin: 0.8rem 0 0.35rem; font-weight: 700; }
    input, textarea { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 8px; font: inherit; }
    button, .bouton { display: inline-block; background: var(--main); color: #fff; border: 0; border-radius: 8px; padding: 0.7rem 1rem; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
    .secondaire { background: transparent; color: var(--dark); }
    .ligne { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; padding: 0.9rem 0; border-bottom: 1px solid #eee; }
    .actions { display: flex; gap: 0.5rem; flex-shrink: 0; }
    .ok { background: #e8f6ee; color: #146c43; padding: 0.75rem 1rem; border-radius: 8px; }
    .err { background: #fdecec; color: #9b1c1c; padding: 0.75rem 1rem; border-radius: 8px; }
    .meta { color: #666; font-size: 0.92rem; }
  </style>
</head>
<body>
  <header>
    <h1>Publications</h1>
    <a href="index.html">Voir le site</a>
  </header>
  <main>
    <?php if ($message !== ''): ?><p class="ok"><?= e($message) ?></p><?php endif; ?>
    <?php if ($erreur !== ''): ?><p class="err"><?= e($erreur) ?></p><?php endif; ?>

    <?php if (!connecte()): ?>
      <form class="panel" method="post">
        <input type="hidden" name="jeton" value="<?= $jeton ?>">
        <label for="mot_de_passe">Mot de passe</label>
        <input id="mot_de_passe" name="mot_de_passe" type="password" required autocomplete="current-password">
        <p><button type="submit">Entrer</button></p>
      </form>
    <?php else: ?>
      <form class="panel" method="post">
        <input type="hidden" name="jeton" value="<?= $jeton ?>">
        <input type="hidden" name="action" value="enregistrer">
        <input type="hidden" name="id" value="<?= e((string) ($edition['id'] ?? '')) ?>">
        <h2><?= $edition ? 'Modifier la publication' : 'Nouvelle publication' ?></h2>
        <label for="titre">Titre</label>
        <input id="titre" name="titre" required maxlength="180" value="<?= e((string) ($edition['titre'] ?? '')) ?>">
        <label for="resume">Résumé</label>
        <textarea id="resume" name="resume" required maxlength="2000" rows="5"><?= e((string) ($edition['resume'] ?? '')) ?></textarea>
        <label for="date">Date</label>
        <input id="date" name="date" type="date" value="<?= e((string) ($edition['date'] ?? '')) ?>">
        <label for="lien">Lien, si l’article est en ligne</label>
        <input id="lien" name="lien" type="url" maxlength="500" placeholder="https://" value="<?= e((string) ($edition['lien'] ?? '')) ?>">
        <p>
          <button type="submit"><?= $edition ? 'Enregistrer' : 'Publier' ?></button>
          <?php if ($edition): ?><a class="bouton secondaire" href="admin.php">Annuler</a><?php endif; ?>
        </p>
      </form>

      <section class="panel">
        <h2>Déjà en ligne</h2>
        <?php $publications = publications_lire(); ?>
        <?php if ($publications === []): ?><p>Aucune publication pour le moment.</p><?php endif; ?>
        <?php foreach ($publications as $item): ?>
          <article class="ligne">
            <div>
              <strong><?= e((string) $item['titre']) ?></strong>
              <?php if (($item['date'] ?? '') !== ''): ?><div class="meta"><?= e((string) $item['date']) ?></div><?php endif; ?>
              <p><?= e((string) $item['resume']) ?></p>
            </div>
            <div class="actions">
              <a class="bouton secondaire" href="admin.php?modifier=<?= e((string) $item['id']) ?>">Modifier</a>
              <form method="post">
                <input type="hidden" name="jeton" value="<?= $jeton ?>">
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                <button class="secondaire" type="submit">Supprimer</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <form method="post">
        <input type="hidden" name="jeton" value="<?= $jeton ?>">
        <button class="secondaire" name="deconnexion" value="1" type="submit">Se déconnecter</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
