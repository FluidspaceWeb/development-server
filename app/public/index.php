<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>It works | Fluidspace Development Server</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            text-align: center;
        }
        h1 {
            font-size: 28px;
            margin: 0 0 16px;
        }
        h3 {
            margin: 4px 0;
        }
        table {
            border-collapse: collapse;
            margin: 0 auto 16px;
            text-align: left;
        }
        table, th, td {
            border: 1px solid #cecece;
        }
        th, td {
            padding: 6px 12px;
            vertical-align: top;
        }
        ul {
            padding-inline-start: 24px;
        }
        pre {
            width: 90%;
            background-color: #f0f0f0;
            white-space: normal;
            margin: 0 auto;
            padding: 6px;
        }
        form {
            text-align: left;
        }
        input[type="text"] {
            margin: 4px 0;
            padding: 4px 6px;
            font-size: 1rem;
        }
        .button {
            -webkit-appearance: none;
            appearance: none;
            display: inline-block;
            margin: 24px 6px;
            border: 1px solid #cecece;
            color: #000;
            padding: 4px 6px;
            text-decoration: none;
            font-size: .875rem;
            cursor: pointer;
            box-shadow: 2px 2px 4px rgba(120,120,120,.15);
        }
        .button:hover {
            background-color: #cecece;
        }
        div.card {
            display: inline-block;
            vertical-align: top;
            width: 24rem;
            box-shadow: 2px 2px 12px rgba(120,120,120,.3);
            padding: 16px;
            margin: 16px;
        }
        p.card-label {
            margin: 0 0 16px;
            padding: 0 0 8px;
            font-weight: bold;
            color: #2e79a8;
            border-bottom: solid 1px #e0e0e0;
        }
        p.card-description {
            text-align: justify;
            font-size: .75rem;
            margin: 16px 0 0;
            padding: 8px 0 0;
            color: #666;
            border-top: solid 1px #e0e0e0;
        }
    </style>
</head>
<body>
    <h1>Fluidspace Development Server [v0.1]</h1>
    <h3>API Client ✅</h3>
    <?php require $_SERVER['DOCUMENT_ROOT'].'/../php/inc/viewDbSummary.php'; showSummary(); ?>

    <div class="card">
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
            When developing a new module of type "app", first create a collection for it here.<br>
            If your module will not store growing data and, needs only some database space to store configuration and other small information then use <i>Props</i> database.<br>
            Note, props are limited to 20 only and are meant solely to store user preferences / configuration / data relations / etc.
        </p>
    </div>

    <div class="card">
        <p class="card-label">Random module ID</p>
        <a href="actions/generateModuleID.php" class="button">Generate Module ID</a>
        <p class="card-description">
            Use this to create a random but valid ID for your module.
        </p>
    </div>
</body>
</html>
