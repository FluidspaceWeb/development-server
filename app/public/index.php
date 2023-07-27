<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <title>Fluidspace Development Server</title>
    <link rel="stylesheet" href="assets/css/index.css" />
</head>
<body>
    <h1>Fluidspace Development Server [v0.1]</h1>
    <h3>API Client ✅</h3>
    <?php require $_SERVER['DOCUMENT_ROOT'].'/../php/inc/viewDbSummary.php'; showSummary(); ?>
    
    <main>
        <div class="card" style="grid-column: span 2">
            <p class="card-label">Random module ID</p>
            <a href="actions/generateModuleID.php" class="button">Generate Module ID</a>
            <p class="card-description">
                Creates a random but valid ID for module development.
            </p>
        </div>
        
        <div class="card" style="grid-column: span 2">
            <p class="card-label">Create new module's collection</p>
            <form action="actions/createCollection.php" method="POST">
                <label>
                    Namespace:
                    <input type="text" name="namespace" placeholder="lowercase, only a-z0-9" pattern="[a-z0-9]+" value="<?= $_ENV['DEVELOPER_NAMESPACE'] ?>" required>
                </label>
                <br>
                <label>
                    Module Name:
                    <input type="text" name="modname" placeholder="lowercase, only a-z0-9" pattern="[a-z0-9]+" required>
                </label>
                <br>
                <p style="margin: 12px 0 0">Select Database:</p>
                <label>
                    <input type="radio" name="dbname" value="db_data" required>
                    Data
                </label>
                <label style="margin-left: 16px">
                    <input type="radio" name="dbname" value="db_props" required>
                    Props
                </label>
                <input type="submit" value="Submit" style="display:block; margin-top: 16px" class="button">
            </form>
            <p class="card-description">
                When developing a new module of type "app", first create a collection for it here.<br><br>
                If your module will not store growing data and, needs only some database space to store configuration and other small information then use <i>Props</i> database.<br><br>
                Note, props are limited to 20 only and are meant solely to store user preferences / configuration / data relations / etc.
            </p>
        </div>

        <div class="card" style="grid-column: span 4">
            <p class="card-label">Add integration config</p>
            <p class="card-description">
                Only required if the REST API that your integration module is accessing uses OAuth2 authentication.
            </p>
            <form action="actions/addIntegrationConfig.php" method="POST">
                <label>
                    Integration Module ID:
                    <input type="text" name="integration_id" placeholder="Module's ID" pattern="[A-Za-z0-9]+" required>
                </label>
                <br>
                <label>
                    Auth Provider Name:
                    <input type="text" name="auth_provider_name" placeholder="Ex: microsoft. use a-z0-9" title="lowercase, only a-z0-9" pattern="[a-z0-9_]+" required>
                </label>
                <br>
                <label>
                    Config:
                    <textarea name="auth_config" placeholder="JSON format configuration, refer docs to learn"></textarea>
                </label>
                <input type="submit" value="Submit" style="display:block; margin-top: 16px" class="button">
            </form>
            
        </div>
    </main>

    <footer>
        <a href="https://fluidspace.app" target="_blank" rel="noopener noreferrer">Website</a>
        <a href="https://github.com/FluidspaceWeb" target="_blank" rel="noopener noreferrer">GitHub</a>
        <a href="https://discord.gg/reeUqaDb2v" target="_blank" rel="noopener noreferrer">Discord</a>
    </footer>
</body>
</html>
