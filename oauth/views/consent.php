<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize <?= escape($client->getName()) ?> · JDM Kenya</title>
    <link href="<?= BASE_PATH ?>/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <style>
        :focus-visible { outline: 3px solid #0d6efd; outline-offset: 3px; }
        .consent-card { max-width: 42rem; }
        @media (max-width: 575.98px) { .consent-actions > * { min-height: 44px; width: 100%; } }
    </style>
</head>
<body class="bg-light">
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 consent-card">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3">Continue to <?= escape($client->getName()) ?>?</h1>
                    <p>This application is requesting the following JDM Kenya account details:</p>
                    <ul>
                        <?php if (in_array('profile', $scopes, true)): ?><li>Your display name</li><?php endif; ?>
                        <?php if (in_array('email', $scopes, true)): ?><li>Your email address and whether it has been verified</li><?php endif; ?>
                    </ul>
                    <p class="text-muted small">Your JDM password, roles, phone number, private profile, and session are never shared.</p>
                    <form method="post" class="d-flex flex-wrap gap-2 consent-actions">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary" type="submit" name="decision" value="approve">Allow and continue</button>
                        <button class="btn btn-outline-secondary" type="submit" name="decision" value="deny">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
