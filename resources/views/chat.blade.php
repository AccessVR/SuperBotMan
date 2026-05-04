<!doctype html>
<html>
<head>
    <title>SuperBotMan Chat</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="{{ \OrchestrateXR\SuperBotMan\Facades\SuperBotMan::asset('resources/css/common.css') }}">
</head>
<body class="relative flex flex-col p-0">
    <div id="chat" v-cloak></div>
    <script>
        window.superbotmanWidget = @json(\OrchestrateXR\SuperBotMan\Facades\SuperBotMan::getClientConfig($config))
    </script>
    <script type="module" src="{{ \OrchestrateXR\SuperBotMan\Facades\SuperBotMan::asset('resources/js/chat.js') }}"></script>
</body>
</html>
